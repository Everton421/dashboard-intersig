<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Em Construção</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
    /* --- Estilos Gerais --- */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Poppins', sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        background: linear-gradient(135deg, #2c3e50, #1a252f);
        color: #ecf0f1;
        overflow: hidden;
        /* Evita barras de rolagem desnecessárias */
    }

    /* --- Container Principal --- */
    .container {
        text-align: center;
        padding: 40px;
        max-width: 600px;
        width: 90%;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 15px;
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        transform: scale(0.95);
        opacity: 0;
        animation: fadeIn 1s ease-out forwards;
    }

    /* --- Ícone --- */
    .icon svg {
        width: 80px;
        height: 80px;
        margin-bottom: 20px;
        fill: #3498db;
        animation: float 3s ease-in-out infinite;
    }

    /* --- Textos --- */
    h1 {
        font-size: 2.8em;
        font-weight: 600;
        margin-bottom: 15px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    }

    p {
        font-size: 1.1em;
        font-weight: 300;
        color: #bdc3c7;
        margin-bottom: 30px;
        max-width: 450px;
        margin-left: auto;
        margin-right: auto;
    }

    /* --- Seção de Contato/Redes Sociais --- */
    .contact-info {
        font-size: 0.9em;
        color: #bdc3c7;
    }

    .contact-info a {
        color: #3498db;
        text-decoration: none;
        font-weight: 400;
        transition: color 0.3s ease;
    }

    .contact-info a:hover {
        color: #5dade2;
    }

    .social-links {
        margin-top: 25px;
    }

    .social-links a {
        display: inline-block;
        margin: 0 15px;
        transition: transform 0.3s ease;
    }

    .social-links a:hover {
        transform: scale(1.2);
    }

    .social-links svg {
        width: 28px;
        height: 28px;
        fill: #bdc3c7;
        transition: fill 0.3s ease;
    }

    .social-links a:hover svg {
        fill: #3498db;
    }

    /* --- Animações --- */
    @keyframes fadeIn {
        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    @keyframes float {
        0% {
            transform: translateY(0px);
        }

        50% {
            transform: translateY(-10px);
        }

        100% {
            transform: translateY(0px);
        }
    }

    /* --- Responsividade para Celulares --- */
    @media (max-width: 600px) {
        h1 {
            font-size: 2.2em;
        }

        p {
            font-size: 1em;
        }

        .container {
            padding: 30px;
        }
    }
    </style>
</head>

<body>

    <div class="container">
        <div class="icon">
            <!-- Ícone SVG de engrenagens -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path
                    d="M19.48,12.75a7.49,7.49,0,1,0-8.95,8.95,7.49,7.49,0,0,0,8.95-8.95ZM12,18a6,6,0,1,1,6-6A6,6,0,0,1,12,18Z" />
                <path d="M12,14a2,2,0,1,0-2-2A2,2,0,0,0,12,14Z" />
                <path d="M19.78,5.32a1,1,0,0,0-1.09.21L17.2,7a1,1,0,0,0,1.41,1.41l1.49-1.49a1,1,0,0,0-.32-1.6Z" />
                <path d="M4.69,18.78a1,1,0,0,0,1.6.32l1.49-1.49a1,1,0,0,0-1.41-1.41L5,17.68A1,1,0,0,0,4.69,18.78Z" />
                <path d="M4.22,5.31A1,1,0,0,0,5.31,4.22L7,5.71A1,1,0,1,0,8.41,7.12L6.92,8.61a1,1,0,0,0-1.6-.32Z" />
                <path
                    d="M18.78,19.31a1,1,0,0,0-.32-1.6L17,16.22a1,1,0,0,0-1.41,1.41l1.49,1.49a1,1,0,0,0,1.6.32A1,1,0,0,0,18.78,19.31Z" />
                <path d="M12.75,4.52a1,1,0,0,0-1.5,0V6a1,1,0,0,0,1.5,0Z" />
                <path d="M11.25,19.48a1,1,0,0,0,1.5,0V18a1,1,0,0,0-1.5,0Z" />
                <path d="M18,11.25H19.5a1,1,0,0,0,0-1.5H18a1,1,0,0,0,0,1.5Z" />
                <path d="M4.5,12.75H6a1,1,0,0,0,0-1.5H4.5a1,1,0,0,0,0,1.5Z" />
            </svg>
        </div>
        <h1>Estamos em Construção</h1>
        <p>Nossa nova página está sendo preparada com muito cuidado e em breve estará no ar. Estamos trabalhando para
            criar uma experiência incrível para você!</p>
        <div class="contact-info">
            Enquanto isso, entre em contato pelo e-mail:
            <a href="mailto:contato@intersig.com.br">suporte@intersig.com.br</a>
        </div>

    </div>

</body>

</html>