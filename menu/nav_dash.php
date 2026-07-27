<?php
require_once '../config/session.php'; 

// 2. SEGURANÇA: Verificar se o usuário está logado. Se não, redirecionar para o login.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 3. Pegar o nome do usuário da sessão
$nomeUsuario = $_SESSION['user_name'];
$nomeEmpresa = $empresa_nome ?? 'Empresa';

// 4. Conectar ao banco de dados e buscar os dados dinâmicos
require_once '../config/config.php'; 

date_default_timezone_set('America/Sao_Paulo');

// Inicializar variáveis
$totalCanceladas = 0;
$totalFaturadas = 0;
$ultimoLoginFormatado = 'Data não disponível'; // Valor padrão

// Data atual no formato do banco (YYYY-MM-DD)
$dataAtual = date('Y-m-d');

// Consulta para notas canceladas no dia atual
$queryCanceladas = "SELECT COUNT(*) as total_canceladas 
                   FROM {$banco_vendas}.cad_nf n
                   JOIN {$banco_vendas}.series s ON s.codigo = n.serie
                   WHERE n.data_saida_entrada = '$dataAtual'
                   AND n.cancelada = 'S'
                   AND s.consid_sped = 'S'";

$resultCanceladas = mysqli_query($dash, $queryCanceladas);
if ($resultCanceladas) {
    $row = mysqli_fetch_assoc($resultCanceladas);
    $totalCanceladas = $row['total_canceladas'];
} else {
    error_log("Erro na consulta de notas canceladas: " . mysqli_error($dash));
}

// Consulta para notas faturadas no dia atual
$queryFaturadas = "SELECT COUNT(*) as total_faturadas 
                  FROM {$banco_vendas}.cad_nf n
                  JOIN {$banco_vendas}.series s ON s.codigo = n.serie
                  WHERE n.data_saida_entrada = '$dataAtual'
                  AND n.cancelada = 'N'
                  AND s.consid_sped = 'S'";

$resultFaturadas = mysqli_query($dash, $queryFaturadas);
if ($resultFaturadas) {
    $row = mysqli_fetch_assoc($resultFaturadas);
    $totalFaturadas = $row['total_faturadas'];
} else {
    error_log("Erro na consulta de notas faturadas: " . mysqli_error($dash));
}

// ADICIONADO: Consulta para buscar o último login do usuário
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    // Supondo que $banco_pulbico esteja definido em seu config.php
    $queryLogin = "SELECT ultimo_login FROM {$banco_publico}.cad_vend WHERE codigo = '{$userId}'";
    $resultLogin = mysqli_query($dash, $queryLogin);

    if ($resultLogin && mysqli_num_rows($resultLogin) > 0) {
        $rowLogin = mysqli_fetch_assoc($resultLogin);
        // Verifica se a data não está vazia antes de formatar
        if (!empty($rowLogin['ultimo_login'])) {
            // Converte a data do formato do banco para o formato brasileiro
            $ultimoLoginFormatado = date('d/m/Y H:i', strtotime($rowLogin['ultimo_login']));
        }
    } else {
        error_log("Erro na consulta de último login ou usuário não encontrado: " . mysqli_error($dash));
    }
}

?>
<nav class="app-header navbar navbar-expand bg-dark" data-bs-theme="dark">
    <!--begin::Container-->
    <div class="container-fluid">
        <!--begin::Start Navbar Links-->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="bi bi-list"></i>
                </a>
            </li>
        </ul>
        <!--end::Start Navbar Links-->

        <!--begin::End Navbar Links-->
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown me-2" id="nav-notificacoes">
                <a class="nav-link" data-bs-toggle="dropdown" href="#" title="Notificações">
                    <i class="bi bi-bell-fill"></i>
                    <?php
        // Contar notificações não lidas para o usuário logado OU para todos
        $userId = $_SESSION['user_id'];
       $queryNotificacoes = "
    SELECT COUNT(*) as total_nao_lidas 
    FROM notificacoes 
    WHERE (usuario_destino IS NULL OR usuario_destino = ?) 
      AND lida = 'N'
      AND ativo = 'S'
      AND (data_envio IS NULL OR data_envio <= NOW())
";
        $stmt = mysqli_prepare($dash, $queryNotificacoes);
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $totalNotificacoes = $row['total_nao_lidas'];

        if ($totalNotificacoes > 0) {
            echo '<span class="navbar-badge badge text-bg-info">' . $totalNotificacoes . '</span>';
        }
        ?>
                </a>

                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <span class="dropdown-item dropdown-header">
                        <span id="notificacoes-count-header"><?= $totalNotificacoes ?></span> Notificação(ões) Não
                        Lida(s)
                    </span>
                    <div class="dropdown-divider"></div>
                    <div id="notificacoes-items">

                        <?php
        // Buscar as notificações não lidas
       $queryListar = "
    SELECT id, titulo, mensagem, data_criacao 
    FROM notificacoes 
    WHERE (usuario_destino IS NULL OR usuario_destino = ?) 
      AND lida = 'N'
      AND ativo = 'S'
      AND (data_envio IS NULL OR data_envio <= NOW())
    ORDER BY data_criacao DESC
    LIMIT 5
";
        $stmtListar = mysqli_prepare($dash, $queryListar);
        mysqli_stmt_bind_param($stmtListar, 'i', $userId);
        mysqli_stmt_execute($stmtListar);
        $resultListar = mysqli_stmt_get_result($stmtListar);

        if (mysqli_num_rows($resultListar) > 0) {
            while ($not = mysqli_fetch_assoc($resultListar)) {
                echo '
                <a href="#" class="dropdown-item notificacao-lida" data-id="' . $not['id'] . '">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-0">' . htmlspecialchars($not['titulo']) . '</h6>
                            <p class="fs-7 text-muted mb-0">' . substr(htmlspecialchars($not['mensagem']), 0, 50) . '...</p>
                            <small class="text-secondary">' . date('d/m H:i', strtotime($not['data_criacao'])) . '</small>
                        </div>
                    </div>
                </a>
                <div class="dropdown-divider"></div>
                ';
            }
        } else {
            echo '<a href="#" class="dropdown-item text-center text-muted">Nenhuma notificação nova</a>';
        }
        ?>

                    </div>
                    <a href="#" class="dropdown-item dropdown-footer" id="notificacoes-atualizar">Atualizar</a>
                </div>
            </li>


            <!--begin::Dropdown de Notas Canceladas-->
            <li class="nav-item dropdown me-2">
                <a class="nav-link" data-bs-toggle="dropdown" href="#" title="Notas Canceladas">
                    <!-- ÍCONE ALTERADO -->
                    <i class="bi bi-file-earmark-x-fill"></i>
                    <span class="navbar-badge badge text-bg-danger"><?php echo $totalCanceladas; ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <a href="#" class="dropdown-item">
                        <!--begin::Message-->
                        <div class="d-flex">
                            <div class="flex-grow-1">
                                <h3 class="dropdown-item-title">
                                    Notas Canceladas
                                </h3>
                                <p class="fs-7">
                                    <strong><?php echo $totalCanceladas; ?></strong>
                                </p>
                                <p class="fs-7 text-secondary">
                                    <i class="bi bi-calendar-fill me-1"></i> <?php echo date('d/m/Y'); ?>
                                </p>
                            </div>
                        </div>
                        <!--end::Message-->
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-footer">Ver Detalhes</a>
                </div>
            </li>
            <!--end::Dropdown de Notas Canceladas-->

            <!--begin::Dropdown de Notas Emitidas-->
            <li class="nav-item dropdown">
                <a class="nav-link" data-bs-toggle="dropdown" href="#" title="Notas Emitidas">
                    <!-- ÍCONE ALTERADO -->
                    <i class="bi bi-receipt"></i>
                    <span class="navbar-badge badge text-bg-warning"><?php echo $totalFaturadas; ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <span class="dropdown-item dropdown-header"><?php echo $totalFaturadas; ?> Notas Faturadas</span>
                    <div class="dropdown-divider"></div>
                    <!-- Outros itens do dropdown podem ser adicionados aqui -->
                    <a href="#" class="dropdown-item dropdown-footer">Ver Todas</a>
                </div>
            </li>
            <!--end::Dropdown de Notas Emitidas-->

              <!--begin::Theme Toggle-->
            <li class="nav-item">
                <a class="nav-link" href="#" id="theme-toggle" role="button" title="Alternar Tema">
                    <i class="bi bi-moon-fill" id="theme-icon"></i>
                </a>
            </li>

            <!--begin::Fullscreen Toggle-->
            <li class="nav-item">
                <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                    <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
                </a>
            </li>
            <!--end::Fullscreen Toggle-->

            <!--begin::User Menu Dropdown-->
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="../assets/img/avatar5.png" class="user-image rounded-circle shadow" alt="User Image" />
                    <span class="d-none d-md-inline">
                        <?php echo htmlspecialchars($nomeUsuario); ?>
                        <small class="text-muted">| <?php echo htmlspecialchars($nomeEmpresa); ?></small>
                    </span>

                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <!--begin::User Image-->
                    <li class="user-header text-bg-primary">
                        <img src="../assets/img/avatar5.png" class="rounded-circle shadow" alt="User Image" />
                        <p>
                            <?php echo htmlspecialchars($nomeUsuario); ?>
                            <small>Último login: <?php echo $ultimoLoginFormatado; ?></small>
                        </p>
                    </li>
                    <!--end::User Image-->
                    <!--begin::Menu Body-->
                    <li class="user-body">
                        <!-- Conteúdo opcional -->
                    </li>
                    <!--end::Menu Body-->
                    <!--begin::Menu Footer-->
                    <li class="user-footer">
                        <a href="../usuario/perfil.php" class="btn btn-default btn-flat">Perfil</a>
                        <a href="logout.php" class="btn btn-default btn-flat float-end">Sair</a>
                    </li>
                    <!--end::Menu Footer-->
                </ul>
            </li>
            <!--end::User Menu Dropdown-->
        </ul>
        <!--end::End Navbar Links-->
    </div>
    <!--end::Container-->
</nav>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>

    // --- LÓGICA DO TEMA (CLARO/ESCURO) ---

(function() {
    const temaSalvo = localStorage.getItem('theme') || 'dark';
    const rootElements = document.querySelectorAll('html, body');

    function aplicarTemaInicial(tema) {
        rootElements.forEach(el => {
            el.setAttribute('data-bs-theme', tema);
            if (tema === 'dark') {
                el.classList.add('dark-mode');
            } else {
                el.classList.remove('dark-mode');
            }
        });
    }
    aplicarTemaInicial(temaSalvo);
})();


$(document).ready(function() {

    // Função para atualizar a UI de notificações
    function atualizarUI(count, html) {
        var badge = $('#nav-notificacoes .navbar-badge');

        // Atualiza o contador no header do dropdown
        $('#notificacoes-count-header').text(count);

        // Atualiza o contador no ícone principal (badge)
        if (count > 0) {
            if (badge.length > 0) {
                badge.text(count);
            } else {
                // Se o badge não existe, cria e adiciona
                $('#nav-notificacoes .nav-link').append('<span class="navbar-badge badge text-bg-info">' +
                    count + '</span>');
            }
        } else {
            // Se o contador for 0, remove o badge
            badge.remove();
        }

        // Atualiza a lista de itens no dropdown
        if (html) {
            $('#notificacoes-items').html(html);
        }
    }

    // Marcar notificação como lida ao clicar
    // Usamos 'body' para garantir que o evento funcione em elementos criados dinamicamente
    $('body').on('click', '.notificacao-lida', function(e) {
        e.preventDefault();
        var notId = $(this).data('id');
        var itemClicado = $(this);

        $.ajax({
            url: '../menu/marcar_notificacao_lida.php',
            type: 'POST',
            data: {
                id: notId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Animação suave para remover o item
                    itemClicado.next('.dropdown-divider').remove();
                    itemClicado.fadeOut(300, function() {
                        $(this).remove();

                        // Se não houver mais itens, mostra a mensagem padrão
                        if ($('#notificacoes-items .dropdown-item').length === 0) {
                            $('#notificacoes-items').html(
                                '<a href="#" class="dropdown-item text-center text-muted">Nenhuma notificação nova</a>'
                                );
                        }
                    });

                    // Atualiza a UI com o novo contador retornado pelo servidor
                    atualizarUI(response.new_count);

                } else {
                    alert('Erro: ' + response.message);
                }
            },
            error: function() {
                alert('Ocorreu um erro de comunicação com o servidor.');
            }
        });
    });

    // Função para verificar novas notificações periodicamente
    function verificarNovasNotificacoes() {
        $.ajax({
            url: '../menu/verificar_notificacoes.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                // Pega o contador atual na tela para evitar piscar se nada mudou
                var contadorAtual = parseInt($('#nav-notificacoes .navbar-badge').text()) || 0;

                // Só atualiza o HTML se o número de notificações for diferente
                if (response.count !== contadorAtual) {
                    console.log("Novas notificações encontradas! Atualizando...");
                    atualizarUI(response.count, response.html);
                }
            },
            error: function() {
                // Não mostra alerta para não incomodar o usuário, apenas loga no console
                console.error("Erro ao verificar notificações.");
            }
        });
    }

    // Inicia a verificação periódica (a cada 15 segundos)
    setInterval(verificarNovasNotificacoes, 15000);


      // --- CONTINUAÇÃO DA LÓGICA DO TEMA (CONTROLES DE UI) ---
    const themeToggle = $('#theme-toggle');
    const themeIcon = $('#theme-icon');
    const rootElements = $('html, body');

    // Função completa que inclui atualização de UI (ícones, etc)
    function aplicarTemaUI(tema) {
        if (tema === 'dark') {
            rootElements.attr('data-bs-theme', 'dark');
            $('body').addClass('dark-mode');
            themeIcon.removeClass('bi-sun-fill').addClass('bi-moon-fill');
            $('.app-header').addClass('bg-dark').attr('data-bs-theme', 'dark').removeClass('bg-light');
            $('.app-sidebar').addClass('bg-dark').attr('data-bs-theme', 'dark').removeClass('bg-light');
            $('.app-footer').addClass('bg-dark').attr('data-bs-theme', 'dark').removeClass('bg-light');
        } else {
            rootElements.attr('data-bs-theme', 'light');
            $('body').removeClass('dark-mode');
            themeIcon.removeClass('bi-moon-fill').addClass('bi-sun-fill');
            $('.app-header').removeClass('bg-dark').attr('data-bs-theme', 'light').addClass('bg-light');
            $('.app-sidebar').removeClass('bg-dark').attr('data-bs-theme', 'light').addClass('bg-light');
            $('.app-footer').removeClass('bg-dark').attr('data-bs-theme', 'light').addClass('bg-light');
        }
        localStorage.setItem('theme', tema);
    }

    // Sincronizar UI com o tema que já foi aplicado no inicio
    const temaAtual = localStorage.getItem('theme') || 'dark';
    aplicarTemaUI(temaAtual);

    // Evento de clique
    themeToggle.on('click', function(e) {
        e.preventDefault();
        const t = localStorage.getItem('theme') || 'dark';
        const novoTema = t === 'dark' ? 'light' : 'dark';
        aplicarTemaUI(novoTema);
    });

});
</script>