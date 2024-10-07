<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width">
</head>

<body class="en uk">
    <table>
        <tr>
            <td>
                <h1><?php echo esc_html($product_name); ?></h1>
                <p>Size: <?php echo esc_html($size); ?></p>
                <p>Color: <?php echo esc_html($color); ?></p>
                <p>Link: <a href="<?php echo esc_url($product_url); ?>"><?php echo esc_html($product_name); ?></a></p>
                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($product_name); ?>">
            </td>
        </tr>
    </table>
</body>

</html>
