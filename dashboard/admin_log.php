<?php
session_start();
require_once '../config/session.php'; 
require_once '../config/config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION["user_name"])) {
    header("Location: ../login.php");
    exit();
}

// Somente o admin pode visualizar os logs
if ($_SESSION["user_name"] != 'INTERSIG') {
    echo "<h3 style='color:red;text-align:center;margin-top:50px;'>Acesso restrito!</h3>";
    exit();
}

// Busca os usuários registrados no log
$sql_usuarios = "SELECT DISTINCT user_id as id_usuario, user_name as nome_usuario FROM logs ORDER BY user_name ASC";
$result_usuarios = $dash->query($sql_usuarios);

// Conta total de acessos
$sql_total = "SELECT COUNT(*) AS total FROM logs";
$total_acessos = $dash->query($sql_total)->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Logs de Acesso</title>

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- jQuery e DataTables -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
    body {
        font-family: "Segoe UI", Arial, sans-serif;
        background: #f4f6f8;
        margin: 20px;
    }

    h2 {
        text-align: center;
        margin-bottom: 10px;
    }

    .contador {
        text-align: center;
        margin-bottom: 20px;
        font-weight: bold;
        color: #007bff;
    }

    .filtros {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
    }

    select,
    input[type="date"],
    button {
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid #ccc;
        font-size: 14px;
    }

    button {
        background: #007bff;
        color: white;
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: 0.3s;
        border: none;
    }

    button:hover {
        background: #0056b3;
    }

    .icon {
        width: 18px;
        height: 18px;
    }

    table.dataTable {
        width: 100% !important;
        font-size: 11px;
    }

    .btn-delete {
        background: #dc3545;
        padding: 5px 10px;
        font-size: 12px;
    }

    .btn-delete:hover {
        background: #c82333;
    }
    </style>
</head>

<body>

    <h2>📋 Logs de Acesso do Sistema</h2>

    <div class="contador">
        Total de acessos registrados: <span id="totalAcessos"><?= $total_acessos ?></span>
    </div>

    <div class="filtros">
        <select id="filtroUsuario">
            <option value="">Todos os usuários</option>
            <?php while($row = $result_usuarios->fetch_assoc()): ?>
            <option value="<?= $row['id_usuario'] ?>"><?= htmlspecialchars($row['nome_usuario']) ?></option>
            <?php endwhile; ?>
        </select>

        <label>De:</label>
        <input type="date" id="dataInicio">

        <label>Até:</label>
        <input type="date" id="dataFim">

        <button id="btnFiltrar"><i data-lucide="search" class="icon"></i>Filtrar</button>
        <button id="btnAtualizar"><i data-lucide="refresh-cw" class="icon"></i>Atualizar</button>
        <button id="limparTodos" style="background:#dc3545;"><i data-lucide="trash-2" class="icon"></i>Limpar
            Todos</button>
    </div>

    <table id="tabelaLogs" class="display nowrap" style="width:100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuário</th>
                <th>ID Usuário</th>
                <th>Endereço da Página</th>
                <th>Data e Hora</th>
                <th>User Agent</th>
                <th>Ações</th>
            </tr>
        </thead>
    </table>

    <script>
    $(document).ready(function() {
        lucide.createIcons();

        let tabela = $('#tabelaLogs').DataTable({
            ajax: {
                url: 'logs_data.php',
                data: function(d) {
                    d.usuario = $('#filtroUsuario').val();
                    d.dataInicio = $('#dataInicio').val();
                    d.dataFim = $('#dataFim').val();
                },
                dataSrc: function(json) {
                    // Atualiza contador de acessos ao recarregar
                    $('#totalAcessos').text(json.length);
                    return json;
                }
            },
            columns: [{
                    data: 'id'
                },
                {
                    data: 'user_name'
                },
                {
                    data: 'user_id'
                },
                {
                    data: 'pagina_url'
                },
                {
                    data: 'acesso',
                    render: function(data, type, row) {
                        // Formata a data/hora para exibição
                        if (type === 'display' || type === 'filter') {
                            if (data) {
                                const date = new Date(data.replace(' ', 'T'));
                                return date.toLocaleString('pt-BR');
                            }
                            return data;
                        }
                        return data;
                    }
                },
                {
                    data: 'user_agent'
                },
                {
                    data: 'id',
                    render: function(data, type, row) {
                        return `<button class="btn-delete" onclick="excluirLog(${data})">
                            <i data-lucide="trash-2" class="icon"></i>Excluir
                        </button>`;
                    },
                    orderable: false,
                    searchable: false
                }
            ],
            responsive: true,
            pageLength: 20,
            order: [
                [0, 'desc']
            ],
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel', 'print'],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
            }
        });

        $('#btnFiltrar').on('click', function() {
            tabela.ajax.reload();
        });

        $('#btnAtualizar').on('click', function() {
            $('#filtroUsuario').val('');
            $('#dataInicio').val('');
            $('#dataFim').val('');
            tabela.ajax.reload();
        });

        // Limpar todos os logs
        $('#limparTodos').click(function() {
            Swal.fire({
                title: "Tem certeza?",
                text: "Todos os registros de log serão apagados!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Sim, apagar tudo",
                cancelButtonText: "Cancelar",
                confirmButtonColor: "#d33"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post("logs_actions.php", {
                        action: "delete_all"
                    }, function(response) {
                        if (response.success) {
                            Swal.fire("Sucesso!", response.message, "success").then(
                                () => {
                                    tabela.ajax.reload();
                                    location.reload();
                                });
                        } else {
                            Swal.fire("Erro!", response.message, "error");
                        }
                    }, "json");
                }
            });
        });
    });

    // Excluir log individual
    function excluirLog(id) {
        Swal.fire({
            title: "Excluir este log?",
            text: "Essa ação não poderá ser desfeita.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Sim, excluir",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#d33"
        }).then((result) => {
            if (result.isConfirmed) {
                $.post("logs_actions.php", {
                    action: "delete_one",
                    id: id
                }, function(response) {
                    if (response.success) {
                        $('#tabelaLogs').DataTable().ajax.reload();
                        Swal.fire("Excluído!", response.message, "success");
                    } else {
                        Swal.fire("Erro!", response.message, "error");
                    }
                }, "json");
            }
        });
    }
    </script>

</body>

</html>