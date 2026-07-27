document.addEventListener('DOMContentLoaded', function() {
    // Toggle de senha
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    
    if(togglePassword) {
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
        });
    }

    // Toggle de tema
    const themeToggle = document.getElementById('themeToggle');
    const htmlElement = document.documentElement;
    
    if(themeToggle) {
        // Verificar tema salvo
        const savedTheme = localStorage.getItem('theme') || 'dark';
        htmlElement.setAttribute('data-bs-theme', savedTheme);
        updateThemeIcon(savedTheme);
        
        themeToggle.addEventListener('click', function() {
            const currentTheme = htmlElement.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            htmlElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
    }

    function updateThemeIcon(theme) {
        const icon = themeToggle.querySelector('i');
        icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        themeToggle.innerHTML = `<i class="${icon.className}"></i> ${theme === 'dark' ? 'Modo Claro' : 'Modo Escuro'}`;
    }

    // Validação do formulário de login
    const loginForm = document.getElementById('loginForm');
    if(loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.classList.add('btn-loading');
        });
    }

   // Validação do formulário de recuperação (REMOVA ou COMENTE esta parte)
/*
const forgotPasswordForm = document.getElementById('forgotPasswordForm');
if(forgotPasswordForm) {
    forgotPasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const email = document.getElementById('recoveryEmail').value;
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Enviando...';
        submitBtn.disabled = true;
        
        fetch('esqueci_senha.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `email=${encodeURIComponent(email)}`
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'E-mail enviado!',
                    text: data.message,
                    timer: 3000
                });
                bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal')).hide();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: data.message
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Ocorreu um erro ao processar sua solicitação.'
            });
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
}
*/

// Em vez do código acima, deixe o formulário submeter normalmente
// e mostre um loading state
const forgotPasswordForm = document.getElementById('forgotPasswordForm');
if(forgotPasswordForm) {
    forgotPasswordForm.addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Enviando...';
        submitBtn.disabled = true;
        
        // O formulário irá submeter normalmente e redirecionar para index.php
        // onde as mensagens serão mostradas
    });
}
});

// Funcionalidade do Lembrar-me
document.addEventListener('DOMContentLoaded', function() {
    const rememberCheckbox = document.getElementById('remember');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    
    // Carregar credenciais salvas quando a página carregar
    if(rememberCheckbox && localStorage.getItem('rememberCredentials')) {
        try {
            const savedCredentials = JSON.parse(localStorage.getItem('rememberCredentials'));
            emailInput.value = savedCredentials.email || '';
            passwordInput.value = savedCredentials.password || '';
            rememberCheckbox.checked = true;
        } catch(e) {
            console.error('Erro ao carregar credenciais salvas:', e);
            // Limpar dados corrompidos
            localStorage.removeItem('rememberCredentials');
        }
    }
    
    // Salvar credenciais quando o formulário for submetido
    const loginForm = document.getElementById('loginForm');
    if(loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const remember = rememberCheckbox.checked;
            const email = emailInput.value;
            const password = passwordInput.value;
            
            if(remember && email && password) {
                // Salvar credenciais no localStorage
                const credentials = {
                    email: email,
                    password: password,
                    timestamp: new Date().getTime()
                };
                localStorage.setItem('rememberCredentials', JSON.stringify(credentials));
            } else {
                // Remover credenciais salvas
                localStorage.removeItem('rememberCredentials');
            }
        });
    }
    
    // Atualizar em tempo real quando o checkbox for alterado
    if(rememberCheckbox) {
        rememberCheckbox.addEventListener('change', function() {
            if(!this.checked) {
                // Se desmarcar, limpar credenciais salvas
                localStorage.removeItem('rememberCredentials');
            }
        });
    }
    
    // Opcional: Salvar enquanto digita (mais conveniente para o usuário)
    let saveTimeout;
    function scheduleSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            if(rememberCheckbox && rememberCheckbox.checked && emailInput.value && passwordInput.value) {
                const credentials = {
                    email: emailInput.value,
                    password: passwordInput.value,
                    timestamp: new Date().getTime()
                };
                localStorage.setItem('rememberCredentials', JSON.stringify(credentials));
            }
        }, 1000);
    }
    
    if(emailInput && passwordInput) {
        emailInput.addEventListener('input', scheduleSave);
        passwordInput.addEventListener('input', scheduleSave);
    }
});