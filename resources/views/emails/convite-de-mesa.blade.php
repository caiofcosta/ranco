{{--
    E-mail HTML de convite para mesa. Tabelas, CSS inline, sem JS e sem fontes externas.
    Dados: docs/email-convite-de-mesa.md
--}}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="pt-BR">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="color-scheme" content="light" />
    <meta name="supported-color-schemes" content="light" />
    <title>{{ $nomeConvidante }} te chamou para “{{ $nomeMesa }}”</title>
    <!--[if mso]>
    <style type="text/css">
        table, td, a { font-family: Arial, Helvetica, sans-serif !important; }
    </style>
    <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#EAE2D5;color:#20201F;">
    <div style="display:none;font-size:1px;color:#EAE2D5;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        {{ $preheader }}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
    </div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EAE2D5;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="660" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:660px;background-color:#F7F3EC;">
                    <tr>
                        <td style="background-color:#20201F;padding:22px 36px;">
                            <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:900;letter-spacing:-0.06em;color:#F7F3EC;">RANÇO<span style="color:#FF684D;">.</span></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#FF684D;height:4px;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:36px 36px 12px 36px;">
                            @if ($mensagemTom !== '')
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EAE2D5;border-left:4px solid #FF684D;">
                                    <tr>
                                        <td width="72" valign="middle" style="padding:16px 8px 16px 16px;">
                                            <img src="{{ $urlTom }}" width="56" alt="" style="display:block;width:56px;height:auto;border:0;outline:none;text-decoration:none;" />
                                        </td>
                                        <td valign="middle" style="padding:16px 20px 16px 8px;font-family:Arial,Helvetica,sans-serif;">
                                            <p style="margin:0 0 4px 0;font-size:11px;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:#20201F;">Tom</p>
                                            <p style="margin:0;font-size:15px;line-height:1.45;color:#20201F;">{{ $mensagemTom }}</p>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 36px 8px 36px;font-family:Arial,Helvetica,sans-serif;">
                            <p style="margin:0 0 10px 0;font-size:11px;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:#706A62;">Você foi chamado</p>
                            <p style="margin:0;font-size:28px;line-height:1.15;font-weight:900;letter-spacing:-0.03em;color:#20201F;">{{ $nomeConvidante }} te convidou para uma mesa.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 36px 8px 36px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FFFFFF;border:1px solid rgba(32,32,31,0.10);border-radius:16px;">
                                <tr>
                                    <td style="padding:22px 24px;font-family:Arial,Helvetica,sans-serif;">
                                        <p style="margin:0 0 8px 0;font-size:11px;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:#706A62;">Mesa</p>
                                        <p style="margin:0 0 8px 0;font-size:20px;line-height:1.25;font-weight:900;color:#20201F;">{{ $nomeMesa }}</p>
                                        <p style="margin:0;font-size:14px;line-height:1.5;color:#706A62;">Tem gente esperando você por lá.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 36px 8px 36px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td bgcolor="#FF684D" style="background-color:#FF684D;border-radius:999px;">
                                        <a href="{{ $urlConvite }}" style="display:inline-block;padding:16px 28px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:900;letter-spacing:0.08em;text-transform:uppercase;text-decoration:none;color:#20201F;">Entrar na mesa</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 36px 8px 36px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;color:#706A62;">
                            Ainda não tem conta? Sem problema.<br />
                            Você cria a sua e entra direto na mesa.
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 36px 12px 36px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="border-top:1px solid rgba(32,32,31,0.12);font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 36px 36px 36px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.55;color:#706A62;">
                            Convite enviado por <strong style="color:#20201F;">{{ $nomeConvidante }}</strong> para este e-mail.<br />
                            Se você não estava esperando isso, pode simplesmente ignorar.
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#20201F;padding:28px 36px;">
                            <p style="margin:0 0 8px 0;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:900;letter-spacing:-0.06em;color:#F7F3EC;">RANÇO<span style="color:#FF684D;">.</span></p>
                            <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.45;color:rgba(247,243,236,0.62);">JOGAR É FÁCIL. PERDER SEM RANÇO É QUE É DIFÍCIL.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
