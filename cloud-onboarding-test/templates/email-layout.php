<?php
/**
 * Email layout wrapper
 *
 * Variables:
 * - $subjectLine
 * - $bodyContent
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($subjectLine, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,400i,700,700i" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <style type="text/css">
        body {
            margin: 0;
            padding: 0;
            font-family: 'Open Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            background-color: #FFFFFF;
        }
        table {
            border-collapse: collapse;
            border-spacing: 0;
        }
        img {
            border: 0;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }
        .wrapper {
            width: 100%;
            background-color: #FFFFFF;
        }
        .content {
            max-width: 650px;
            margin: 0 auto;
        }
        .header-image {
            width: 100%;
            max-width: 650px;
            height: auto;
            display: block;
        }
        h1 {
            font-family: 'IBM Plex Sans', sans-serif;
            font-size: 30px;
            font-weight: normal;
            color: #002C5A;
            text-align: center;
            line-height: 36px;
            margin: 25px 0;
        }
        .content-text,
        .content-body p,
        .content-body ul li,
        .content-body ol li {
            font-family: 'Open Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 21px;
            color: #333333;
            margin: 0 0 10px 0;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            background-color: #5E9728;
            color: #FFFFFF !important;
            font-family: 'IBM Plex Sans', sans-serif;
            font-size: 16px;
            font-weight: normal;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 0px;
            line-height: 19.2px;
        }
        .button:hover {
            background-color: #75bd32;
        }
        .alert-box {
            background-color: #FFF3CD;
            border: 1px solid #FFE69C;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .alert-box p {
            margin: 0;
            color: #856404;
            font-size: 14px;
            line-height: 21px;
        }
        .single-app-notice {
            background-color: #E7F3FF;
            border-left: 4px solid #002C5A;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            background-color: #F7F7F7;
            padding: 20px;
            text-align: center;
            margin-top: 40px;
        }
        .footer-logo {
            height: 60px;
            margin: 20px 0;
        }
        .separator {
            border-bottom: 1px solid #CCCCCC;
            margin: 20px 0;
        }
        strong {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <table width="100%" cellspacing="0" cellpadding="0" role="presentation">
            <tr>
                <td align="center">
                    <table class="content" width="650" cellspacing="0" cellpadding="0" role="presentation">
                        <tr>
                            <td align="center" style="padding: 0;">
                                <img src="https://content.app-us1.com/cdn-cgi/image/dpr=2,fit=scale-down,format=auto,onerror=redirect,width=650/oJDko/2024/11/08/77abaa42-083d-4ce9-b242-bd68f6aed80f.jpeg" alt="4BS Cloud" class="header-image">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <h1><strong><?= htmlspecialchars($subjectLine, ENT_QUOTES, 'UTF-8') ?></strong></h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 20px;">
                                <div class="content-body">
                                    <?= $bodyContent ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <table width="100%" cellspacing="0" cellpadding="0" role="presentation" class="footer">
            <tr>
                <td align="center">
                    <div class="separator"></div>
                    <img src="https://content.app-us1.com/cdn-cgi/image/dpr=2,fit=scale-down,format=auto,onerror=redirect,width=650/oJDko/2024/10/30/8444c8a9-e6e3-43cc-aa1d-28bcd5d9f239.png" alt="4BS" class="footer-logo">
                    <p style="font-size: 12px; color: #666666; margin: 10px 0;">
                        4 Business Software NV<br>
                        Nijverheidsstraat 37, 2570 Duffel, Belgium<br>
                        <a href="https://4bs.com" style="color: #002C5A;">www.4bs.com</a> |
                        <a href="mailto:support@4bs.com" style="color: #002C5A;">support@4bs.com</a> |
                        <a href="tel:+3234911700" style="color: #002C5A;">+32 3 491 17 00</a>
                    </p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
