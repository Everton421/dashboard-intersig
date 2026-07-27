<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Incluir o autoload do PHPMailer
require_once './bibliotecas/PHPMailer/PHPMailer.php';
require_once './bibliotecas/PHPMailer/SMTP.php';
require_once './bibliotecas/PHPMailer/Exception.php';

class EmailSender {
    private $mail;
    private $config;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->loadConfig();
        $this->configureMailer();
    }

    private function loadConfig() {
        // Configurações do e-mail - ajuste conforme seu servidor
        $this->config = [
            'host' => 'mail.intersig.com.br', // Ex: smtp.gmail.com, smtp.office365.com
            'username' => 'suporte@intersig.com.br',
            'password' => 'Intersig@9227', // Use senha de aplicativo para Gmail
            'port' => 587, // 587 para TLS, 465 para SSL
            'encryption' => 'starttls', // tls ou ssl
            'from_email' => 'suporte@intersig.com.br',
            'from_name' => 'Digital Eletro',
            'reply_to' => 'suporte@intersig.com.br',
            'reply_to_name' => 'Suporte - Digital Eletro'
        ];


    }

    private function configureMailer() {
        try {
            // Configurações do servidor
            $this->mail->isSMTP();
            $this->mail->Host = $this->config['host'];
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->config['username'];
            $this->mail->Password = $this->config['password'];
            $this->mail->SMTPSecure = $this->config['encryption'];
            $this->mail->Port = $this->config['port'];
            
            // Configurações gerais
            $this->mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mail->addReplyTo($this->config['reply_to'], $this->config['reply_to_name']);
            $this->mail->CharSet = 'UTF-8';
            $this->mail->Encoding = 'base64';
            

        } catch (Exception $e) {
            error_log("Erro na configuração do PHPMailer: " . $e->getMessage());
            throw new Exception("Erro na configuração do serviço de e-mail.");
        }
    }

    public function sendResetPasswordEmail($toEmail, $toName, $resetToken) {
        try {
            
            $this->mail->clearAddresses();
            
            
            $this->mail->addAddress($toEmail, $toName);
            
            
            $this->mail->Subject = 'Redefinição de Senha';
            
            
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_senha.php?token=" . $resetToken;
            
            
            $this->mail->isHTML(true);
            $this->mail->Body = $this->getResetPasswordHTMLTemplate($toName, $resetLink);
            
            
            $this->mail->AltBody = $this->getResetPasswordTextTemplate($toName, $resetLink);
            
            
            $this->mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail de reset: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    private function getResetPasswordHTMLTemplate($name, $resetLink) {
        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Redefinição de Senha</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    padding: 20px; 
                    background: #f9f9f9; 
                }
                .header { 
                    background: linear-gradient(135deg, #6366f1, #4f46e5); 
                    color: white; 
                    padding: 30px; 
                    text-align: center; 
                    border-radius: 10px 10px 0 0; 
                }
                .content { 
                    background: white; 
                    padding: 30px; 
                    border-radius: 0 0 10px 10px; 
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
                }
                .button { 
                    display: inline-block; 
                    background: linear-gradient(135deg, #6366f1, #4f46e5); 
                    color: white; 
                    padding: 12px 30px; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    font-weight: bold; 
                    margin: 20px 0; 
                }
                .footer { 
                    text-align: center; 
                    margin-top: 20px; 
                    color: #666; 
                    font-size: 12px; 
                }
                .warning { 
                    background: #fff3cd; 
                    border: 1px solid #ffeaa7; 
                    padding: 15px; 
                    border-radius: 5px; 
                    margin: 15px 0; 
                    color: #856404; 
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Redefinição de Senha</h1>
                </div>
                <div class='content'>
                    <h2>Olá, {$name}!</h2>
                    <p>Recebemos uma solicitação para redefinir a senha da sua conta no<strong>Intersig Sistema</strong>.</p>
                    
                    <p>Clique no botão abaixo para criar uma nova senha:</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$resetLink}' class='button'>Redefinir Minha Senha</a>
                    </div>
                    
                    <p>Ou copie e cole o seguinte link no seu navegador:</p>
                    <p style='word-break: break-all; color: #6366f1;'>{$resetLink}</p>
                    
                    <div class='warning'>
                        <strong>⚠️ Importante:</strong> 
                        <ul>
                            <li>Este link expira em 1 hora</li>
                            <li>Se você não solicitou esta redefinição, ignore este e-mail</li>
                            <li>Sua senha atual continuará funcionando até que você a altere</li>
                        </ul>
                    </div>
                    
                    <p>Se você tiver alguma dúvida, entre em contato com nosso suporte.</p>
                    
                    <p>Atenciosamente,<br>
                    <strong>Equipe Intersig Sistemas</strong></p>
                </div>
                <div class='footer'>
                    <p>Este é um e-mail automático, por favor não responda.</p>
                    <p>&copy; " . date('Y') . " Intersig Sistemas. Todos os direitos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function getResetPasswordTextTemplate($name, $resetLink) {
        return "
        Redefinição de Senha
        
        Olá, {$name}!
        
        Recebemos uma solicitação para redefinir a senha da sua conta.
        
        Para redefinir sua senha, acesse o link abaixo:
        {$resetLink}
        
        Este link expira em 1 hora.
        
        Se você não solicitou esta redefinição, ignore este e-mail.
        
        Atenciosamente,
        Equipe Intersig Sistemas
        
        ---
        E-mail automático, não responda.
        ";
    }

    // Método para teste de configuração
    public function testConnection() {
        try {
            $this->mail->smtpConnect();
            $this->mail->smtpClose();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    // Método para enviar e-mail genérico
    public function sendEmail($toEmail, $toName, $subject, $htmlBody, $textBody = '') {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail, $toName);
            $this->mail->Subject = $subject;
            $this->mail->isHTML(true);
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = $textBody ?: strip_tags($htmlBody);
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail: " . $this->mail->ErrorInfo);
            return false;
        }
    }
}

// Função auxiliar para uso rápido
function sendResetEmail($email, $name, $token) {
    $emailSender = new EmailSender();
    return $emailSender->sendResetPasswordEmail($email, $name, $token);
}

// Exemplo de uso:
// if(sendResetEmail('usuario@email.com', 'João Silva', 'token_gerado')) {
//     echo 'E-mail enviado com sucesso!';
// } else {
//     echo 'Erro ao enviar e-mail.';
// }
?>