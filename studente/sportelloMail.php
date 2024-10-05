<!--
/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
-->

<?php

  require_once '../common/checkSession.php';
  ruoloRichiesto('studente','segreteria-didattica','dirigente');

  $full_mail_body = '
  <html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
  <head>
  <!--[if gte mso 9]>
  <xml>
    <o:OfficeDocumentSettings>
      <o:AllowPNG/>
      <o:PixelsPerInch>96</o:PixelsPerInch>
    </o:OfficeDocumentSettings>
  </xml>
  <![endif]-->
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <!--[if !mso]><!--><meta http-equiv="X-UA-Compatible" content="IE=edge"><!--<![endif]-->
    <title></title>
    
      <style type="text/css">
        table, td { color: #000000; } a { color: #3598db; text-decoration: underline; }
  @media only screen and (min-width: 570px) {
    .u-row {
      width: 550px !important;
    }
    .u-row .u-col {
      vertical-align: top;
    }
  
    .u-row .u-col-100 {
      width: 550px !important;
    }
  
  }
  
  @media (max-width: 570px) {
    .u-row-container {
      max-width: 100% !important;
      padding-left: 0px !important;
      padding-right: 0px !important;
    }
    .u-row .u-col {
      min-width: 320px !important;
      max-width: 100% !important;
      display: block !important;
    }
    .u-row {
      width: calc(100% - 40px) !important;
    }
    .u-col {
      width: 100% !important;
    }
    .u-col > div {
      margin: 0 auto;
    }
  }
  body {
    margin: 0;
    padding: 0;
  }
  
  table,
  tr,
  td {
    vertical-align: top;
    border-collapse: separate;
  }
  
  p {
    margin: 0;
  }
  
  .ie-container table,
  .mso-container table {
    table-layout: fixed;
  }
  
  * {
    line-height: inherit;
  }
  
  a[x-apple-data-detectors="true"] {
    color: inherit !important;
    text-decoration: none !important;
  }
  
  </style>
    
    
  
  <!--[if !mso]><!--><link href="https://fonts.googleapis.com/css?family=Lato:400,700&display=swap" rel="stylesheet" type="text/css"><link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet" type="text/css"><!--<![endif]-->
  
  </head>
  
  <body class="clean-body u_body" style="margin: 0;padding: 0;-webkit-text-size-adjust: 100%;background-color: #293c4b;color: #000000">
    <!--[if IE]><div class="ie-container"><![endif]-->
    <!--[if mso]><div class="mso-container"><![endif]-->
    <table style="border-collapse: separate;table-layout: fixed;border-spacing: 0;mso-table-lspace: 0pt;mso-table-rspace: 0pt;vertical-align: top;min-width: 320px;Margin: 0 auto;background-color: #293c4b;width:100%" cellpadding="0" cellspacing="0">
    <tbody>
    <tr style="vertical-align: top">
      <td style="word-break: break-word;border-collapse: collapse !important;vertical-align: top">
      <!--[if (mso)|(IE)]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" style="background-color: #293c4b;"><![endif]-->
      
  
  <div class="u-row-container" style="padding: 0px;background-color: transparent">
    <div class="u-row" style="Margin: 0 auto;min-width: 320px;max-width: 550px;overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: transparent;">
      <div style="border-collapse: separate ;display: table;width: 100%;background-color: transparent;">
        <!--[if (mso)|(IE)]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding: 0px;background-color: transparent;" align="center"><table cellpadding="0" cellspacing="0" border="0" style="width:550px;"><tr style="background-color: transparent;"><![endif]-->
        
  <!--[if (mso)|(IE)]><td align="center" width="550" style="width: 550px;padding: 0px;border-top: 0px solid transparent;border-left: 0px solid transparent;border-right: 0px solid transparent;border-bottom: 0px solid transparent;" valign="top"><![endif]-->
  <div class="u-col u-col-100" style="max-width: 320px;min-width: 550px;display: table-cell;vertical-align: top;">
    <div style="width: 100% !important;">
    <!--[if (!mso)&(!IE)]><!--><div style="padding: 0px;border-top: 0px solid transparent;border-left: 0px solid transparent;border-right: 0px solid transparent;border-bottom: 0px solid transparent;"><!--<![endif]-->
    
  <table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
    <tbody>
      <tr>
        <td style="overflow-wrap:break-word;word-break:break-word;padding:15px 10px;font-family:arial,helvetica,sans-serif;" align="left">
          
  <table width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
      <td style="padding-right: 0px;padding-left: 0px;" align="center">
        <img align="center" border="0" src="../img/logo_Buonarroti.png" alt="Logo" title="Logo" width="505" unselectable="on"/>
        </a>
      </td>
    </tr>
  </table>
  
        </td>
      </tr>
    </tbody>
  </table>
  
    <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]-->
    </div>
  </div>
  <!--[if (mso)|(IE)]></td><![endif]-->
        <!--[if (mso)|(IE)]></tr></table></td></tr></table><![endif]-->
      </div>
    </div>
  </div>
  
  
  
  <div class="u-row-container" style="padding: 0px;background-color: transparent">
    <div class="u-row" style="Margin: 0 auto;min-width: 320px;max-width: 550px;overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: #3598db;">
      <div style="border-collapse: separate;display: table;width: 100%;background-color: transparent;">
        <!--[if (mso)|(IE)]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding: 0px;background-color: transparent;" align="center"><table cellpadding="0" cellspacing="0" border="0" style="width:550px;"><tr style="background-color: #3598db;"><![endif]-->
        
  <!--[if (mso)|(IE)]><td align="center" width="550" style="background-color: #169179;width: 550px;padding: 0px;border-top: 0px solid transparent;border-left: 0px solid transparent;border-right: 0px solid transparent;border-bottom: 0px solid transparent;" valign="top"><![endif]-->
  <div class="u-col u-col-100" style="max-width: 320px;min-width: 550px;display: table-cell;vertical-align: top;">
    <div style="background-color: #169179;width: 100% !important;">
    <!--[if (!mso)&(!IE)]><!--><div style="padding: 0px;border-top: 0px solid transparent;border-left: 0px solid transparent;border-right: 0px solid transparent;border-bottom: 0px solid transparent;"><!--<![endif]-->
    
  <table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
    <tbody>
      <tr>
        <td style="overflow-wrap:break-word;word-break:break-word;padding:30px 10px 0px 15px;font-family:arial,helvetica,sans-serif;" align="left">
          
    <h1 style="padding-bottom: 10px; margin: 1px; color: #ecf0f1; line-height: 140%; text-align: center; word-wrap: break-word; font-weight: normal; font-family: Montserrat,sans-serif; font-size: 22px;">
      {titolo}
      
    </h1>
  
        </td>
      </tr>
    </tbody>
  </table>
  
    <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]-->
    </div>
  </div>
  <!--[if (mso)|(IE)]></td><![endif]-->
        <!--[if (mso)|(IE)]></tr></table></td></tr></table><![endif]-->
      </div>
    </div>
  </div>
  
  
  
  <div class="u-row-container" style="padding: 0px;background-image: url();background-repeat: no-repeat;background-position: center top;background-color: transparent">
    <div class="u-row" style="Margin: 0 auto;min-width: 320px;max-width: 550px;overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: #3598db;">
      <div style="border-collapse: separate;display: table;width: 100%;background-color: transparent;">
        <!--[if (mso)|(IE)]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding: 0px;background-image: url();background-repeat: no-repeat;background-position: center top;background-color: transparent;" align="center"><table cellpadding="0" cellspacing="0" border="0" style="width:550px;"><tr style="background-color: #3598db;"><![endif]-->
        
  <!--[if (mso)|(IE)]><td align="center" width="550" style="background-color: #169179;width: 550px;padding: 0px;border-top: 0px solid transparent;border-left: 0px solid transparent;border-right: 0px solid transparent;border-bottom: 0px solid transparent;" valign="top"><![endif]-->
  <div class="u-col u-col-100" style="max-width: 320px;min-width: 550px;display: table-cell;vertical-align: top;">
    <div style="background-color: #169179;width: 100% !important;">
    <!--[if (!mso)&(!IE)]><!--><div style="padding: 0px;border-top: 0px solid transparent;border-left: 0px solid transparent;border-right: 0px solid transparent;border-bottom: 0px solid transparent;"><!--<![endif]-->
    
  <table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
    <tbody>
      <tr>
        <td style="overflow-wrap:break-word;word-break:break-word;padding:0px 15px;font-family:arial,helvetica,sans-serif;" align="left">
          
  <table width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
      <td style="padding-right: 0px;padding-left: 0px;" align="center">
        
        <img align="center" border="0" src="../img/Calendar.png" alt="" title="" style="outline: none;text-decoration: none;-ms-interpolation-mode: bicubic;clear: both;display: inline-block !important;border: none;height: auto;float: none;width: 100%;max-width: 520px;" width="520"/>
        
      </td>
    </tr>
  </table>
  
        </td>
      </tr>
    </tbody>
  </table>
  
    <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]-->
    </div>
  </div>
  <!--[if (mso)|(IE)]></td><![endif]-->
        <!--[if (mso)|(IE)]></tr></table></td></tr></table><![endif]-->
      </div>
    </div>
  </div>
  
  
  
  <div class="u-row-container" style="padding: 0px;background-color: transparent">
    <div class="u-row" style="Margin: 0 auto;min-width: 320px;max-width: 550px;overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: #ffffff;">
      <div style="border-collapse: separate;display: table;width: 100%;background-color: transparent;">
        <!--[if (mso)|(IE)]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding: 0px;background-color: transparent;" align="center"><table cellpadding="0" cellspacing="0" border="0" style="width:550px;"><tr style="background-color: #ffffff;"><![endif]-->
        
  <!--[if (mso)|(IE)]><td align="center" width="550" style="width: 550px;padding: 0px 20px 20px;border-top: 0px solid transparent;border-left: 0px solid transparent;border-right: 0px solid transparent;border-bottom: 0px solid transparent;" valign="top"><![endif]-->
  <div class="u-col u-col-100" style="max-width: 320px;min-width: 550px;display: table-cell;vertical-align: top;">
    <div style="width: 100% !important;">
    <!--[if (!mso)&(!IE)]><!--><div style="padding: 0px 20px 20px;border-top: 0px solid transparent;border-left: 0px solid transparent;border-right: 0px solid transparent;border-bottom: 0px solid transparent;"><!--<![endif]-->
    
  <table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
    <tbody>
      <tr>
        <td style="overflow-wrap:break-word;word-break:break-word;padding:20px 10px 10px 15px;font-family:arial,helvetica,sans-serif;" align="left">
          
    <h3 style="margin: 0px; color: #293c4b; line-height: 140%; text-align: left; word-wrap: break-word; font-weight: normal; font-family: Montserrat,sans-serif; font-size: 18px;">
      <strong>Buongiorno {Destinatario},</strong>
    </h3>
  
        </td>
      </tr>
    </tbody>
  </table>
  
  <table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
    <tbody>
      <tr>
        <td style="overflow-wrap:break-word;word-break:break-word;padding:10px;font-family:arial,helvetica,sans-serif;" align="left">
          
    <div style="color: #656e72; line-height: 140%; text-align: left; word-wrap: break-word;">
      <p style="font-size: 14px; line-height: 140%;">{Messaggio}</p>
      <br>
      <table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="1">
        <tbody>
          <tr>
            <td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;width: 40%;background-color:darkgreen;"  align="left">
              <div style="color: #656e72; line-height: 140%; text-align: left; word-wrap: break-word;">
                <p style="font-size: 14px; line-height: 140%; text-align: center;"><span style="font-size: 16px; line-height: 22.4px; font-family: Lato, sans-serif; color:#FFFFFF"><strong>Data sportello</strong></span></p>
              </div>
            </td>
            <td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color:aquamarine"  align="left">
              <div style="color: #656e72; line-height: 140%; text-align: left; word-wrap: break-word;">
                <p style="font-size: 14px; line-height: 140%; text-align: center;"><span style="font-size: 16px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>{Data}</strong></span></p>
              </div>
            </td>
          </tr>
          <tr>
            <td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color: darkgreen; width: 40%;"  align="left">
              <div style="color: #656e72; line-height: 140%; text-align: left; word-wrap: break-word;">
                <p style="font-size: 14px; line-height: 140%; text-align: center;"><span style="font-size: 16px; line-height: 22.4px; font-family: Lato, sans-serif;color:#FFFFFF"><strong>Orario sportello</strong></span></p>
              </div>
            </td>
            <td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color:aquamarine"  align="left">
              <div style="color: #656e72; line-height: 140%; text-align: left; word-wrap: break-word;">
                <p style="font-size: 14px; line-height: 140%; text-align: center;"><span style="font-size: 16px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>{Orario}</strong></span></p>
              </div>
            </td>
          </tr>
          <tr>
            <td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;width: 40%;background-color: darkgreen;"  align="left">
              <div style="color: #656e72; line-height: 140%; text-align: left; word-wrap: break-word;">
                <p style="font-size: 14px; line-height: 140%; text-align: center;"><span style="font-size: 16px; line-height: 22.4px; font-family: Lato, sans-serif;color:#FFFFFF"><strong>Docente</strong></span></p>
              </div>
            </td>
            <td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color:aquamarine"  align="left">
              <div style="color: #656e72; line-height: 140%; text-align: left; word-wrap: break-word;">
                <p style="font-size: 14px; line-height: 140%; text-align: center;"><span style="font-size: 16px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>{Docente}</strong></span></p>
              </div>
            </td>
          </tr>
          <tr>
            <td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;width: 40%;background-color: darkgreen;"  align="left">
              <div style="color: #656e72; line-height: 140%; text-align: left; word-wrap: break-word;">
                <p style="font-size: 14px; line-height: 140%; text-align: center;"><span style="font-size: 16px; line-height: 22.4px; font-family: Lato, sans-serif;color:#FFFFFF"><strong>Materia</strong></span></p>
              </div>
            </td>
            <td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color:aquamarine"  align="left">
              <div style="color: #656e72; line-height: 140%; text-align: left; word-wrap: break-word;">
                <p style="font-size: 14px; line-height: 140%; text-align: center;"><span style="font-size: 16px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>{Materia}</strong></span></p>
              </div>
            </td>
          </tr>
          <tr>
            <td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;width: 40%;background-color: darkgreen;"  align="left">
              <div style="color: #656e72; line-height: 140%; text-align: left; word-wrap: break-word;">
                <p style="font-size: 14px; line-height: 140%; text-align: center;"><span style="font-size: 16px; line-height: 22.4px; font-family: Lato, sans-serif;color:#FFFFFF"><strong>Aula</strong></span></p>
              </div>
            </td>
            <td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color:aquamarine"  align="left">
              <div style="color: #656e72; line-height: 140%; text-align: left; word-wrap: break-word;">
                <p style="font-size: 14px; line-height: 140%; text-align: center;"><span style="font-size: 16px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>{Aula}</strong></span></p>
              </div>
            </td>
          </tr>

        </tbody>
      </table>

    </div>
  
        </td>
      </tr>
    </tbody>
  </table>
  
    <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]-->
    </div>
  </div>
  <!--[if (mso)|(IE)]></td><![endif]-->
        <!--[if (mso)|(IE)]></tr></table></td></tr></table><![endif]-->
      </div>
    </div>
  </div>  
  
  <div class="u-row-container" style="padding: 0px;background-color: transparent">
    <div class="u-row" style="Margin: 0 auto;min-width: 320px;max-width: 550px;overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: #ecf0f1;">
      <div style="border-collapse: collapse;display: table;width: 100%;background-color: transparent;">
        <!--[if (mso)|(IE)]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding: 0px;background-color: transparent;" align="center"><table cellpadding="0" cellspacing="0" border="0" style="width:550px;"><tr style="background-color: #ecf0f1;"><![endif]-->
        
  <!--[if (mso)|(IE)]><td align="center" width="550" style="width: 550px;padding: 0px;border-top: 0px solid transparent;border-left: 0px solid transparent;border-right: 0px solid transparent;border-bottom: 0px solid transparent;" valign="top"><![endif]-->
  <div class="u-col u-col-100" style="max-width: 320px;min-width: 550px;display: table-cell;vertical-align: top;">
    <div style="width: 100% !important;">
    <!--[if (!mso)&(!IE)]><!--><div style="padding: 0px;border-top: 0px solid transparent;border-left: 0px solid transparent;border-right: 0px solid transparent;border-bottom: 0px solid transparent;"><!--<![endif]-->
    
  <table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
    <tbody>
      <tr>
        <td style="overflow-wrap:break-word;word-break:break-word;padding:25px 10px 10px;font-family:arial,helvetica,sans-serif;" align="left">';

      $linkCalendar = 'https://calendar.google.com/calendar/render?action=TEMPLATE&dates=' . $datetime_sportello . 'Z%2F' . $datetime_fine_sportello . 'Z&details=' . urlencode("Sportello di " . $materia . ' - Aula ' . urlencode($luogo)) . '&location=' . urlencode('Istituto Tecnico Tecnologico Buonarroti, Via Brigata Acqui, 15, 38122 Trento TN, Italia') . '&text=' . urlencode("Sportello di " . $materia . " - Aula " . $luogo);
      info("link promemoria su Google Calendar: " . $linkCalendar);

      $full_mail_body .= '
    <div style="color: #000000; line-height: 140%; text-align: center; word-wrap: break-word;">
      <a href="https://calendar.google.com/calendar/render?action=TEMPLATE&dates=20241007T140000Z%2F20241007T154000Z&details=Sportello+di+A.AUT.-P.C.I.-TEC.PROF.&location=Istituto+Tecnico+Tecnologico+Buonarroti%2C+Via+Brigata+Acqui%2C+15%2C+38122+Trento+TN%2C+Italia%20Aula%20&text=Sportello+di+A.AUT.-P.C.I.-TEC.PROF.+Aula+" target="_blank" style="font-size: 16px; line-height: 22.4px; text-decoration: none; color: #000000; font-weight: bold;"><img align="center" border="0" src="../img/google_calendar_icon.png" alt="Logo" title="Logo" width="100" unselectable="on"></img>
        Aggiungi un promemoria sul tuo Google Calendar</a>
    </div>

        </td>
      </tr>
    </tbody>
  </table>
  
  <table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
    <tbody>
      <tr>
        <td style="overflow-wrap:break-word;word-break:break-word;padding:0px 10px 10px;font-family:arial,helvetica,sans-serif;" align="left">

  <div align="center">
    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-spacing: 0; border-collapse: collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;font-family:arial,helvetica,sans-serif;"><tr><td style="font-family:arial,helvetica,sans-serif;" align="center"><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="https://www.buonarroti.tn.it/" style="height:39px; v-text-anchor:middle; width:248px;" arcsize="10.5%" stroke="f" fillcolor="#7db00e"><w:anchorlock/><center style="color:#FFFFFF;font-family:arial,helvetica,sans-serif;"><![endif]-->
      <p style="padding: 10px; box-sizing: border-box;display: inline-block;font-family:arial,helvetica,sans-serif;text-decoration: none;-webkit-text-size-adjust: none;text-align: center;color: #FFFFFF; background-color: #7db00e; border-radius: 4px;-webkit-border-radius: 4px; -moz-border-radius: 4px; width:auto; max-width:100%; overflow-wrap: break-word; word-break: break-word; word-wrap:break-word; mso-border-alt: none;">';

    $full_mail_body .= ' Mail generata da GestOre<br>' . $__settings->local->nomeIstituto . '</p>';
    
    $full_mail_body .= '
    </div>
  
        </td>
      </tr>
    </tbody>
  </table>
  
    <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]-->
    </div>
  </div>
  <!--[if (mso)|(IE)]></td><![endif]-->
        <!--[if (mso)|(IE)]></tr></table></td></tr></table><![endif]-->
      </div>
    </div>
  </div>
  
  
      <!--[if (mso)|(IE)]></td></tr></table><![endif]-->
      </td>
    </tr>
    </tbody>
    </table>
    <!--[if mso]></div><![endif]-->
    <!--[if IE]></div><![endif]-->
  </body>
  
  </html>';
  // inverto format data
  $data_array = explode("-", $data);
  $data = $data_array[2] . "-" . $data_array[1] . "-" . $data_array[0];
  //
  $full_mail_body = str_replace("{titolo}","ISCRIZIONE SPORTELLO",$full_mail_body);
  $full_mail_body = str_replace("{Destinatario}",strtoupper($studente_cognome) . " " . strtoupper($studente_nome),$full_mail_body);
  $full_mail_body = str_replace("{Messaggio}","hai ricevuto questa mail come conferma della tua iscrizione allo sportello qui riportato",$full_mail_body);
  $full_mail_body = str_replace("{Data}",$data,$full_mail_body);
  $full_mail_body = str_replace("{Orario}",$ora,$full_mail_body);
  $full_mail_body = str_replace("{Docente}",strtoupper($docente_cognome . " " . $docente_nome),$full_mail_body);
  $full_mail_body = str_replace("{Materia}",$materia,$full_mail_body);
  $full_mail_body = str_replace("{Aula}",$luogo,$full_mail_body);
  info("NUOVO " . $full_mail_body);
  




