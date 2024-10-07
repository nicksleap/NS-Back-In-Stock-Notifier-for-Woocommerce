class NotifierAdmin {
  constructor() {
    addEventListener('load', this.onLoad.bind(this));
  }
  onLoad() {
    //console.log('load');
    jQuery('#notify-sortby').on('change', this.addSortBy.bind(this));
    jQuery('#notify-filter-status').on('change', this.addStatusFilter.bind(this));
    jQuery('#notify-filter-date').on('change', this.addDateFilter.bind(this));
    jQuery('#add-search-btn').on('click', this.addSearch.bind(this));
    jQuery('#send-emails-btn').on('click', this.sendEmails.bind(this));
    jQuery('#export-emails-btn').on('click', this.exportEmails.bind(this));
  }

  addSortBy(e) {
    e.preventDefault();
    let sel = jQuery(e.target);
    if (sel.val()) window.location.href = window.location.href + '&sortby=' + sel.val();
  }

  addStatusFilter(e) {
    e.preventDefault();
    let sel = jQuery(e.target);
    window.location.href = window.location.href + '&status=' + sel.val();
  }

  addDateFilter(e) {
    e.preventDefault();
    let inp = jQuery(e.target);
    if (inp.val()) window.location.href = window.location.href + '&date=' + inp.val();
  }

  addSearch(e) {
    e.preventDefault();
    let inp = jQuery('#notify-search').val();
    if (inp) window.location.href = window.location.href + '&like=' + inp;
  }

  sendEmails(e) {
    e.preventDefault();
    let btn = jQuery(e.target);
    btn.find('i').addClass('loading');
    let data = {
      action: 'uco_send_emails',
      security: notifier.ajax_nonce,
    };
    jQuery.ajax({
      type: "post",
      dataType: "json",
      url: notifier.ajax_url,
      data: data,
      success: function (res) {
        btn.find('i').removeClass('loading');
        if (res.success == true) {
          alert('Done.');
          window.location.reload();
        } else {
          if (res.data) alert(res.data.msg)
        }
      },
      error: function (error) {
        alert('Something went wrong');
        btn.find('i').removeClass('loading');
      }
    });
  }

  exportEmails(e) {
    e.preventDefault();
    let btn = jQuery(e.target);
    btn.find('i').addClass('loading');
    let data = {
      action: 'uco_export_emails',
      security: notifier.ajax_nonce,
    };
    jQuery.ajax({
      type: "post",
      dataType: "json",
      url: notifier.ajax_url,
      data: data,
      success: function (res) {
        btn.find('i').removeClass('loading');
        if (res.success == true) {
          //alert('Done.');
          if (res.data && res.data.filename) window.location.href = res.data.filename;
        } else {
          if (res.data) alert(res.data.msg)
        }
      },
      error: function (error) {
        alert('Something went wrong');
        btn.find('i').removeClass('loading');
      }
    });
  }
}

new NotifierAdmin();
