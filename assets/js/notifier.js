class Notifier {
  constructor() {
    addEventListener('load', this.onLoad.bind(this));
  }
  onLoad() {
    //console.log('load');
    this.InitBlock();
  }

  InitBlock() {
    var self = this;

    let outofstock = jQuery('.single-product .woocommerce-variation-availability .stock.out-of-stock');
    let formAdd = jQuery('form.variations_form.cart');
    let tmpl = jQuery('#tmpl-notify-me-form').html();
    let productId = jQuery('form.variations_form.cart input[name="product_id"]').val(),
      variationID = jQuery('form.variations_form.cart input[name="variation_id"]').val(),
      product = jQuery('.product_title').first().text();
    //size = jQuery('input#'+btn.attr('for')).val(),
    //color = jQuery('table.variations .iconic-wlv-variations__selection').text();

    if (outofstock.length > 0 && tmpl.length > 0) {
      //console.log(product);
      jQuery('.single-product.woocommerce div.product form.cart div.quantity').hide();
      jQuery('button.notify-me-available, .notify-me-container').remove();
      formAdd.find('button[type="submit"]').hide();
      formAdd.append('<button class="notify-me-available button alt" style="display:none"> תודיעו לי כשהמוצר במלאי </button>'); //notify me when available
      tmpl = tmpl.replaceAll(/{{product}}/g, product);
      tmpl = tmpl.replaceAll(/{{productId}}/g, productId);
      tmpl = tmpl.replaceAll(/{{variationID}}/g, variationID);
      //tmpl = tmpl.replaceAll(/{{color}}/g, color);
      //tmpl = tmpl.replaceAll(/{{size}}/g, size);
      formAdd.append(tmpl);
      jQuery('.notify-me-available.button').slideDown(50);

      jQuery('button.notify-me-available').on('click', function (e) {
        e.preventDefault();
        let btn = jQuery(e.target);
        btn.toggleClass('expand');
        jQuery('.notify-me-container').toggle();
        if (btn.hasClass('expand')) {
          //jQuery([document.documentElement, document.body]).animate({ scrollTop: jQuery("#notify-me-section").offset().top }, 1000);
        }
      })
      jQuery('a.notify-cancel-btn').on('click', function (e) {
        e.preventDefault();
        //addBtn.find('button[type="submit"]').show();
        //jQuery('.single-product .variation-radios label').removeClass('selected-size');
        //jQuery('.single-product .variation-radios input[type="radio"]').prop('checked', false);
        jQuery('.notify-me-container').toggle();
        //jQuery('.notify-me-container').remove()
      });
      jQuery('a.notify-update-btn').on('click', self.sendEmail.bind(self));
    }

  }

  clickVariation(e) {
    var self = this;

    //let btn = jQuery(e.target);
    //let addBtn = jQuery('.woocommerce-variation-add-to-cart.variations_button');
    /*let formAdd = jQuery('form.variations_form.cart');
    let productId = jQuery('form.variations_form.cart input[name="product_id"]').val(),
        product = jQuery('h1').first().text(),
        size = jQuery('input#'+btn.attr('for')).val(),
        color = jQuery('table.variations .iconic-wlv-variations__selection').text();
        //variation = formAdd.data('product_variations');*/
    //        if (btn.hasClass('hide-variation')) {
    e.preventDefault();
    /*let tmpl = jQuery('#tmpl-notify-me-form').html();
    jQuery('.single-product .variation-radios label').removeClass('selected-size');
    jQuery('.single-product .variation-radios input[type="radio"]').prop('checked', false);
    btn.addClass('selected-size');
    console.log(product);
    jQuery('button.notify-me-available, .notify-me-container').remove();
    addBtn.find('button[type="submit"]').hide();
    addBtn.append('<button class="notify-me-available button alt"> תודיעו לי כשחוזר למלאי </button>'); //notify me when available
    tmpl = tmpl.replaceAll(/{{product}}/g, product);
    tmpl = tmpl.replaceAll(/{{productId}}/g, productId);
    tmpl = tmpl.replaceAll(/{{color}}/g, color);
    tmpl = tmpl.replaceAll(/{{size}}/g, size);
    formAdd.append(tmpl);*/

    jQuery('button.notify-me-available').on('click', function (e) {
      e.preventDefault();
      let btn = jQuery(e.target);
      btn.toggleClass('expand');
      jQuery('.notify-me-container').toggle();
      if (btn.hasClass('expand')) {
        jQuery([document.documentElement, document.body]).animate({ scrollTop: jQuery("#notify-me-section").offset().top }, 1000);
      }
    })
    jQuery('a.notify-cancel-btn').on('click', function (e) {
      e.preventDefault();
      addBtn.find('button[type="submit"]').show();
      jQuery('.single-product .variation-radios label').removeClass('selected-size');
      jQuery('.single-product .variation-radios input[type="radio"]').prop('checked', false);
      jQuery('button.notify-me-available, .notify-me-container').remove()
    });
    jQuery('a.notify-update-btn').on('click', self.sendEmail.bind(self));
    /*        } else {
                addBtn.find('button[type="submit"]').show();
                jQuery('.single-product .variation-radios label').removeClass('selected-size');
                jQuery('.single-product .variation-radios input[type="radio"]').prop('checked', false);
                jQuery('button.notify-me-available, .notify-me-container').remove()
            }*/
  }

  sendEmail(e) {
    e.preventDefault();
    let btn = jQuery(e.target);
    let form = jQuery('.notify-me-form');
    let email = form.find('input[name="nm_email"]').val(),
      check = form.find('input[name="nm_check"]:checked'),
      product = form.find('input[name="nm_product_id"]').val(),
      variationID = form.find('input[name="nm_variation_id"]').val(),
      size = form.find('input[name="nm_size"]').val(),
      notifyResponceBox = form.find('.notify-me-success');

    if (!email || !check.length) {
      if (!email) form.find('input[name="nm_email"]').addClass('error');
      if (!check.length) form.find('input[name="nm_check"]').addClass('error');
      return;
    } else {
      form.find('input').removeClass('error');
    }

    btn.find('i').addClass('loading');
    let data = {
      action: 'uco_send_notifier_email',
      email: email,
      product: product,
      variation_id : variationID,
      security: notifier.ajax_nonce,
    };
    jQuery.ajax({
      type: "post",
      dataType: "json",
      url: notifier.ajax_url,
      data: data,
      success: function (res) {
        if (res.success == true && typeof res.data !== 'undefined') {
          jQuery('.notify-me-available.button').hide().after(`<div class="notify-me-success">${res.data.msg}</div>`);
          jQuery('a.notify-cancel-btn').trigger('click')
        }
        jQuery(notifyResponceBox).text(res.data.msg).removeClass('hidden');
      },
      error: function (error) {
        console.error(error)
        jQuery(notifyResponceBox).text('Something went wrong').removeClass('hidden');;
      },
      complete: function () {
        btn.find('i').removeClass('loading');
      }
    });
  }

}

new Notifier();
