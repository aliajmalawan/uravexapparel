<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#F5F3EE;font-family:Arial,Helvetica,sans-serif;color:#111111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F3EE;">
  <tr><td align="center" style="padding:24px 12px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border:1px solid #E5E5E5;">
      <tr><td align="center" style="background:#0B0B0B;padding:22px 24px;border-bottom:2px solid #C89B3C;">
        <span style="font-family:Arial,Helvetica,sans-serif;font-size:20px;letter-spacing:8px;font-weight:bold;color:#FFFFFF;">URAVEX</span><br>
        <span style="font-size:10px;letter-spacing:6px;color:#E5C16B;">APPAREL</span>
      </td></tr>
      <tr><td style="padding:28px 24px;font-size:15px;line-height:1.6;">
        <?= $body ?>
      </td></tr>
      <tr><td style="padding:18px 24px;background:#F5F3EE;font-size:12px;line-height:1.5;color:#666666;">
        Need help? Reply to this email or write to <a href="mailto:<?= e($brand['support']) ?>" style="color:#8A6A22;"><?= e($brand['support']) ?></a>.<br>
        <a href="<?= e($brand['url']) ?>" style="color:#8A6A22;">URAVEX Apparel</a> · Global clothing manufacturing &amp; exports
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
