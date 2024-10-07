<?php

/** Notify me form */
?>
<script type="text/html" id='tmpl-notify-me-form'>
    <section class="notify-me-container" id="notify-me-section" style="display:none">

        <div class="notify-me-form">
            <form class="notify-me-form validation-form" method="post">
                <input type="hidden" name="nm_product_id" value="{{productId}}">
                <input type="hidden" name="nm_variation_id" value="{{variationID}}">
                <!--<label class="" for="shopperEmail">Email Address</label>-->
                <input class="input-box" type="email" name="nm_email" id="shopperEmail" title="Email" placeholder="Email" required="">
                <div class="notify-me-success hidden"></div>
                <div class="message"></div>
                <div class="notify-me-action">
                    <a class="button primary notify-update-btn "><i></i>Send</a>
                    <a class="button notify-cancel-btn"><i></i>Cansel</a>
                </div>
            </form>
        </div>
    </section>
</script>
