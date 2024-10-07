<?php
/** Notify me form */
?>
<script type="text/html" id='tmpl-notify-me-form'>
    <section class="notify-me-container" id="notify-me-section" style="display:none">
        <h4 class="notify-me-label">
            <!--notify me when available-->
        </h4>
        <div class="product-content-form-notify-me-desc notify-me-desc">
            <p class="product-content-form-notify-me-desc-wrapper">
                יש להקליד כתובת אימייל על מנת שנוכל לעדכן אותך כשהפריט יחזור למלאי
                <?php //Please enter your email so we can alert you when the <span class="prod-attr">{{product}}</span> in <span class="attr-selected prod-attr">COLOR {{color}}, Size {{size}}</span> is back in stock. We promise not to spam you. You will only be notified once. ?>
            </p>
        </div>
        <div class="notify-me-form">
            <form class="notify-me-form validation-form" method="post">
                <input type="hidden" name="nm_product_id" value="{{productId}}">
                <input type="hidden" name="nm_variation_id" value="{{variationID}}">
                <!--<label class="" for="shopperEmail">Email Address</label>-->
                <input class="input-box" type="email" name="nm_email" id="shopperEmail" title="Email" placeholder="Email" required="">
                <label for="nm_check"><input type="checkbox" id="nm_check" name="nm_check" required="">אני מאשר/ת לקבל עדכונים בדבר זמינות המלאי של הפריט מעת לעת</label>
                <div class="notify-me-success hidden"></div>
                <div class="message"></div>
                <div class="notify-me-action">
                    <a class="button primary notify-update-btn "><i></i>אישור</a>
                    <a class="button notify-cancel-btn"><i></i>ביטול</a>
                </div>
            </form>
        </div>
    </section>
</script>
