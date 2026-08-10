<?php 
require_once('../zvinodiwa/conexao.php');
// Define o fuso horário para America/Sao_Paulo
date_default_timezone_set('America/Sao_Paulo');


?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fluxo Syma</title>
    <script src="https://unpkg.com/tableexport@5.0.2/dist/js/tableexport.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.21/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>



</head>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=JetBrains+Mono:wght@400;700&display=swap');

:root {
    --bg-main: #0a0a0b;
    --bg-card: #121214;
    --bg-hover: #1a1a1c;
    --accent-green: #00ff88;
    --accent-red: #ff3e3e;
    --accent-blue: #0ea5e9;
    --text-main: #e2e8f0;
    --text-dim: #94a3b8;
    --border-color: #2a2a2c;
    --font-main: 'Inter', sans-serif;
    --font-mono: 'JetBrains Mono', monospace;
}

body {
    background-color: var(--bg-main);
    background-image:
        radial-gradient(circle at 10% 20%, rgba(0, 255, 136, 0.03) 0%, transparent 40%),
        radial-gradient(circle at 90% 80%, rgba(14, 165, 233, 0.03) 0%, transparent 40%);
    color: var(--text-main);
    font-family: var(--font-main);
    margin: 0;
    padding: 20px;
    min-height: 100vh;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Header & Logo */
header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 20px;
}

.logo-container img {
    filter: brightness(0) invert(1);
    opacity: 0.9;
}

.header-title h1 {
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    text-transform: uppercase;
    margin: 0;
    color: var(--accent-green);
}

/* Filters - Control Panel Style */
.filter-panel {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    padding: 24px;
    margin-bottom: 30px;
    position: relative;
}

.filter-panel::before {
    content: 'CONTROL PANEL';
    position: absolute;
    top: -10px;
    left: 20px;
    background: var(--bg-main);
    padding: 0 10px;
    font-family: var(--font-mono);
    font-size: 10px;
    color: var(--accent-green);
    letter-spacing: 0.2em;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: end;
}

.filter-group label {
    display: block;
    font-size: 0.75rem;
    color: var(--text-dim);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.form-control-custom {
    background: #1a1a1c;
    border: 1px solid var(--border-color);
    color: var(--text-main);
    padding: 10px 12px;
    width: 100%;
    font-size: 0.9rem;
    border-radius: 2px;
    transition: all 0.2s ease;
}

.form-control-custom:focus {
    border-color: var(--accent-green);
    outline: none;
    background: #202022;
    box-shadow: 0 0 10px rgba(0, 255, 136, 0.1);
}

.btn-update {
    background: var(--accent-green);
    color: #000;
    border: none;
    padding: 10px 24px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
    height: 42px;
}

.btn-update:hover {
    background: #00d672;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 255, 136, 0.2);
}

/* KPI Cards */
.kpi-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}

.kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    padding: 12px;
    border-right: 3px solid var(--accent-blue);
    transition: transform 0.16s ease;
}

.kpi-card:hover {
    transform: translateY(-2px);
    background: var(--bg-hover);
}

.kpi-card.positive {
    border-right-color: var(--accent-green);
}

.kpi-card.negative {
    border-right-color: var(--accent-red);
}

.kpi-label {
    color: var(--text-dim);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 12px;
}

.kpi-value {
    font-family: var(--font-mono);
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-main);
}

.kpi-value.green {
    color: var(--accent-green);
}

.kpi-value.red {
    color: var(--accent-red);
}

/* Modern Table */
.table-container {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    margin-bottom: 20px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

th {
    background: #1a1a1c;
    color: var(--text-dim);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 16px 20px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

td {
    padding: 14px 20px;
    border-bottom: 1px solid rgba(42, 42, 44, 0.5);
    color: var(--text-main);
}

tr:hover td {
    background: var(--bg-hover);
}

.mono {
    font-family: var(--font-mono);
    font-weight: 500;
}

.text-positive {
    color: var(--accent-green) !important;
}

.text-negative {
    color: var(--accent-red) !important;
}

/* Expansion Transitions */
.detalhes-syma,
.detalhes-receber-vencidos,
.detalhes-pagar-sem-previsoes,
.detalhes-ammo,
.detalhes-receber-vencidos-ammo,
.detalhes-pagar-sem-previsoes-ammo {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-left: 2px solid var(--accent-blue);
}

/* Search Bar Industrial */
.search-row {
    margin-top: 15px;
    position: relative;
}

.search-input {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid var(--border-color);
    color: var(--text-main);
    padding: 8px 12px 8px 35px;
    width: 100%;
    max-width: 400px;
    font-size: 0.85rem;
    border-radius: 2px;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-dim);
    font-size: 0.8rem;
}


/* Print Optimization */
@media print {

    .filter-panel,
    .action-panel,
    .btn-update,
    .expand-btn,
    header {
        display: none !important;
    }

    body {
        background: white;
        color: black;
        padding: 0;
    }

    .container {
        max-width: 100%;
        border: none;
    }

    .table-container {
        border: 1px solid #ddd;
        max-height: none;
        overflow: visible;
    }

    .kpi-card {
        border: 1px solid #ddd;
        background: #f9f9f9 !important;
        color: black !important;
    }

    .kpi-value {
        color: black !important;
    }

    td,
    th {
        color: black !important;
        border-bottom: 1px solid #ddd !important;
    }

    tr.hidden-on-print {
        display: none !important;
    }
}

/* Actions Footer */
.action-panel {
    display: flex;
    gap: 12px;
    margin-top: 30px;
}

.btn-action {
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-main);
    padding: 10px 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-action:hover {
    border-color: var(--accent-blue);
    color: var(--accent-blue);
    background: rgba(14, 165, 233, 0.05);
}

.btn-action.success:hover {
    border-color: var(--accent-green);
    color: var(--accent-green);
    background: rgba(0, 255, 136, 0.05);
}

/* Dashboard Layout Tweaks */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 20px;
    margin-bottom: 30px;
}

.chart-container {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    padding: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.chart-container canvas {
    max-width: 220px;
    max-height: 220px;
    width: 100%;
    height: auto;
}

@media (max-width: 1200px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container">
    <header>
        <div class="logo-container">
            <img src="data:image/png;base64,<?php echo base64_encode(file_get_contents("img/logo.png")); ?>" width="160"
                height="53">
        </div>
        <div class="header-title">
            <h1>Fluxo de Caixa Geral</h1>
        </div>
    </header>

    <nav class="filter-panel">
        <form method="post">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Receber / Pagar Dia</label>
                    <div style="display: flex; gap: 10px;">
                        <input class="form-control-custom" type="date" name="data-iniciosp"
                            value="<?php echo date('Y-m-d'); ?>">
                        <input class="form-control-custom" type="date" name="data-fimsp"
                            value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="filter-group">
                    <label>Provisão 30 Dias</label>
                    <div style="display: flex; gap: 10px;">
                        <?php $dateInicio = date('Y-m-d', strtotime('+1 day'));?>
                        <input class="form-control-custom" type="date" name="data-iniciopro"
                            value="<?php echo $dateInicio; ?>">
                        <?php $dateTermino = date('Y-m-d', strtotime('+30 days', strtotime($dateInicio)));?>
                        <input class="form-control-custom" type="date" name="data-fimpro"
                            value="<?php echo $dateTermino; ?>">
                    </div>
                </div>

                <div class="filter-group">
                    <label>Receber Vencidos</label>
                    <div style="display: flex; gap: 10px;">
                        <input class="form-control-custom" type="date" name="data-iniciorve" value="2022-01-01">
                        <input class="form-control-custom" type="date" name="data-fimrve"
                            value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <button type="submit" name="update-button" class="btn-update">Explorar Dados</button>
            </div>
            <button type="button" class="btn-action" onclick="setDateRange('next30')">Próximos 30d</button>
</div>
<!-- Table Search -->
<div class="search-row">
    <span class="search-icon">🔍</span>
    <input type="text" id="table-search" class="search-input" placeholder="Filtrar dados do relatório..."
        onkeyup="filterTable()">
</div>
</form>
<script>
function setDateRange(type) {
    const today = new Date().toISOString().split('T')[0];
    const next30 = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    const startOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    const endOfMonth = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0];

    if (type === 'today') {
        document.getElementsByName('data-iniciosp')[0].value = today;
        document.getElementsByName('data-fimsp')[0].value = today;
    } else if (type === 'month') {
        document.getElementsByName('data-iniciosp')[0].value = startOfMonth;
        document.getElementsByName('data-fimsp')[0].value = endOfMonth;
    } else if (type === 'next30') {
        document.getElementsByName('data-iniciopro')[0].value = today;
        document.getElementsByName('data-fimpro')[0].value = next30;
    }
    document.querySelector('.btn-update').click();
}
</script>
</nav>
<?php 
     if (isset($_POST["update-button"])):
        $dataIniciosp = $_POST["data-iniciosp"];
        $dataFimsp = $_POST["data-fimsp"];
        $dataIniciopro = $_POST["data-iniciopro"];
        $dataFimpro = $_POST["data-fimpro"];
        $dataIniciorve = $_POST["data-iniciorve"];
        $dataFimrve = $_POST["data-fimrve"];
   
      // Consulta 1: saldo a receber cartões de crédito SYMA
      $sql1 = "SELECT SUM(valor_liq) AS valorcartao FROM ".$prefixf.".cartao_mvtos WHERE DATA_CREDITO >= CURRENT_DATE() AND baixado = 'N'";
      $resultado1 = mysqli_query($conexao, $sql1);
      $linha1 = mysqli_fetch_assoc($resultado1);
      echo "Pagar Dia - Início: " . $dataIniciosp . " - Término: " . $dataFimsp . "<br>";
      echo "Prov. 30DD - Início: " . $dataIniciopro . " - Término: " . $dataFimpro . "<br>";
      echo "Rec. Vencidos- Início: " . $dataIniciorve . " - Término: " . $dataFimrve . "<br>";
      
      

      // Consulta 2: saldo a receber vencidos SYMA

    $sql2 = "SELECT SUM(valor) AS saldoreceber FROM ".$prefixf.".ct_receb WHERE valor_pago = 0 AND vencimento >= '2022-01-01' AND vencimento BETWEEN '{$dataIniciorve}' AND '{$dataFimrve}'
      AND agrup_destino = 0 AND caucao = '0000-00-00' AND inadimplencia = '0000-00-00' AND cartorio = '0000-00-00' AND advogado = '0000-00-00' AND 
      cobradora = '0000-00-00' AND correio = '0000-00-00' AND spc_serasa = '0000-00-00' AND cliente NOT IN (9605, 18376, 113232, 152874,195645)";

$resultado2 = mysqli_query($conexao, $sql2);
$linha2 = mysqli_fetch_assoc($resultado2);


  // Consulta para detalhar o saldo a receber vencidos dos clientes específicos da SYMA
      $sql_clientes_syma_vencidos = "SELECT cliente, SUM(valor) as saldo_cliente_vencido 
          FROM ".$prefixf.".ct_receb 
          WHERE valor_pago = 0 
          AND vencimento >= '2022-01-01'
          AND vencimento BETWEEN '{$dataIniciorve}' AND '{$dataFimrve}'
          AND agrup_destino = 0 
          AND caucao = '0000-00-00'	
          AND inadimplencia = '0000-00-00' 
          AND cartorio = '0000-00-00' 
          AND advogado = '0000-00-00'
          AND cobradora = '0000-00-00' 
          AND correio = '0000-00-00'	
          AND spc_serasa = '0000-00-00'  
          AND cliente IN (9605, 18376,113232,152874,195645)
          GROUP BY cliente";
      $resultado_clientes_syma_vencidos = mysqli_query($conexao, $sql_clientes_syma_vencidos);


      // Consulta 3: saldo a pagar sem previsões SYMA
      $sql3 = "SELECT SUM(valor) AS saldopagar FROM ".$prefixf.".ct_pagar WHERE valor_pago = 0 AND vencimento > CURRENT_DATE()  AND valor > 0.01 AND agrup_destino = 0 AND provisao = 'N' AND fornecedor NOT IN (10500,392)  AND inadimplencia = '0000-00-00' ";
      $resultado3 = mysqli_query($conexao, $sql3);
      $linha3 = mysqli_fetch_assoc($resultado3);

      // Consulta 4: saldo a pagar do dia SYMA
      $sql4 = "SELECT SUM(valor) AS saldopagardia FROM ".$prefixf.".ct_pagar WHERE valor_pago = 0 AND valor > 0.01 AND vencimento BETWEEN '{$dataIniciosp}' AND '{$dataFimsp}' 
      AND valor > 0.01 AND agrup_destino = 0 AND provisao = 'N' AND inadimplencia = '0000-00-00'  AND fornecedor NOT IN (10500,13611,392)";
      $resultado4 = mysqli_query($conexao, $sql4);
      $linha4 = mysqli_fetch_assoc($resultado4);

     // Consulta 5:provisoes a pagar 30 dias
     $sql5 = "select sum(valor) as provisoes from ".$prefixf.".ct_pagar where valor_pago = 0 AND inadimplencia = '0000-00-00'  AND valor > 0.01 and vencimento  BETWEEN '{$dataIniciopro}' AND '{$dataFimpro}' 
     and agrup_destino = 0  and provisao = 'S' and fornecedor NOT IN (10500,13611,392)";
     $resultado5 = mysqli_query($conexao, $sql5);
     $linha5 = mysqli_fetch_assoc($resultado5);

     // Consulta 5:provisoes a pagar antigas
     $sqlproantigo = "select sum(valor) as provisoesantigas from ".$prefixf.".ct_pagar where valor_pago = 0 AND inadimplencia = '0000-00-00'  AND valor > 0.01 and  vencimento < '$dataIniciopro'
     and agrup_destino = 0  and provisao = 'S' and fornecedor NOT IN (10500,13611,392)";
     $resultadoproantigo = mysqli_query($conexao, $sqlproantigo);
     $linhaproantigo = mysqli_fetch_assoc($resultadoproantigo);

      // Consulta 6:Saldo do dia
      $sql6 = "SELECT sum(saldo_conciliado) AS total_saldo FROM (
        SELECT saldo_conciliado FROM ".$prefixf.".ct_mvto WHERE codigo IN (79,84,25,22,160,163,136,63,141,137,148,19,135,173,175,176,177,178,182) 
        UNION ALL
        SELECT ra.saldo_conciliado FROM ".$prefixjrf.".ct_mvto ra WHERE ra.codigo IN (1,16,17,18,19,12,23,24,25,26)
      ) AS t";
      $resultado6 = mysqli_query($conexao, $sql6);
      $linha6 = mysqli_fetch_assoc($resultado6);
 
        // Consulta 7:saldo a receber  syma
        $sql7 = "select sum(valor) as saldorecebergeral from ".$prefixf.".ct_receb where valor_pago = 0 and vencimento >= '{$dataIniciosp}'
        and agrup_destino = 0 and caucao = '0000-00-00'	and inadimplencia = '0000-00-00' and cartorio = '0000-00-00' and advogado = '0000-00-00'
              and cobradora = '0000-00-00' and correio = '0000-00-00'	and spc_serasa = '0000-00-00'  and cliente not in(9605,18376,113232,152874,195645)";
      $resultado7 = mysqli_query($conexao, $sql7);
      $linha7 = mysqli_fetch_assoc($resultado7);


     
      // Consulta para detalhar o saldo a receber dos clientes específicos da SYMA
      $sql_clientes_syma_details = "SELECT cliente, SUM(valor) as saldo_cliente 
          FROM ".$prefixf.".ct_receb 
          WHERE valor_pago = 0 
          AND vencimento >= '{$dataIniciosp}'
          AND agrup_destino = 0 
          AND caucao = '0000-00-00'	
          AND inadimplencia = '0000-00-00' 
          AND cartorio = '0000-00-00' 
          AND advogado = '0000-00-00'
          AND cobradora = '0000-00-00' 
          AND correio = '0000-00-00'	
          AND spc_serasa = '0000-00-00'  
          AND cliente IN (9605,18376,113232,152874,195645)
          GROUP BY cliente";
      $resultado_clientes_syma_details = mysqli_query($conexao, $sql_clientes_syma_details);

       $sql_pagar_sem_previsoes_details = "SELECT fornecedor, SUM(valor) AS saldo_fornecedor
      FROM ".$prefixf.".ct_pagar WHERE valor_pago = 0 AND vencimento > CURRENT_DATE()  AND valor > 0.01 AND agrup_destino = 0 AND provisao = 'N' 
      AND fornecedor  IN (10500,392)  AND inadimplencia = '0000-00-00'GROUP BY fornecedor";
     $resultado_pagar_sem_previsoes_details = mysqli_query($conexao, $sql_pagar_sem_previsoes_details);
   


              // Consulta 8:saldo a receber  Ammo
              $sql8 = "select sum(valor) as saldorecebergerala from ".$prefixjrf.".ct_receb where valor_pago = 0  and vencimento >= '{$dataIniciosp}'
              and agrup_destino = 0 and caucao = '0000-00-00'	and inadimplencia = '0000-00-00' and cartorio = '0000-00-00' and advogado = '0000-00-00'
              and cobradora = '0000-00-00' and correio = '0000-00-00'	and spc_serasa = '0000-00-00' and cliente not in(84537,9605,70065)";
              $resultado8 = mysqli_query($conexao, $sql8);
              $linha8 = mysqli_fetch_assoc($resultado8);
 
         // Consulta 9: saldo a receber vencidos Ammo
      $sql9 = "SELECT SUM(valor) AS saldorecebera FROM ".$prefixjrf.".ct_receb WHERE valor_pago = 0 AND vencimento BETWEEN '{$dataIniciorve}' AND '{$dataFimrve}'  and agrup_destino = 0 and caucao = '0000-00-00'	and inadimplencia = '0000-00-00' and cartorio = '0000-00-00' and advogado = '0000-00-00'
      and cobradora = '0000-00-00' and correio = '0000-00-00'	and spc_serasa = '0000-00-00' and cliente not in(84537,9605,70065)";
      $resultado9 = mysqli_query($conexao, $sql9);
      $linha9 = mysqli_fetch_assoc($resultado9);

     
      // Consulta 3: saldo a pagar sem previsões AMMO
       $sql10 = "SELECT SUM(valor) AS saldopagara FROM ".$prefixjrf.".ct_pagar WHERE valor_pago = 0 AND vencimento > CURRENT_DATE() AND agrup_destino = 0 AND provisao = 'N'  
       AND fornecedor NOT IN (392,12888,12156) AND valor > 0.01";
       $resultado10 = mysqli_query($conexao, $sql10);
       $linha10 = mysqli_fetch_assoc($resultado10);

        
      // Consulta 4: saldo a pagar do dia AMMO
     
      $sql11 = "SELECT SUM(valor) AS saldopagardiaa FROM ".$prefixjrf.".ct_pagar WHERE valor_pago = 0 AND vencimento BETWEEN '{$dataIniciosp}' AND '{$dataFimsp}' 
       AND agrup_destino = 0 AND provisao = 'N' AND fornecedor NOT IN (392,12888,12156) AND valor > 0.01";
     // echo "Consulta SQL: " . $sql11 . "<br>";
      $resultado11 = mysqli_query($conexao, $sql11);
      $linha11 = mysqli_fetch_assoc($resultado11);

     // Consulta 5:provisoes a pagar 30 AMMO
     $sql12 = "select sum(valor) as provisoesa from ".$prefixjrf.".ct_pagar where valor_pago = 0  and vencimento BETWEEN '{$dataIniciopro}' AND '{$dataFimpro}' 
     and agrup_destino = 0  and provisao = 'S' AND fornecedor NOT IN (392,12888,12156) AND valor > 0.01";
     $resultado12 = mysqli_query($conexao, $sql12);
     $linha12 = mysqli_fetch_assoc($resultado12);

     // Consulta 16: saldo a receber cartões de crédito AMMO
     $sql16 = "SELECT SUM(valor_liq) AS valorcartaoa FROM ".$prefixjrf.".cartao_mvtos WHERE DATA_CREDITO >= CURRENT_DATE() AND baixado = 'N'";
     $resultado16 = mysqli_query($conexao, $sql16);
     $linha16 = mysqli_fetch_assoc($resultado16);

     // Consulta para detalhar o saldo a receber dos clientes específicos da AMMO
     $sql_clientes_ammo_details = "SELECT cliente, SUM(valor) as saldo_cliente 
         FROM ".$prefixjrf.".ct_receb 
         WHERE valor_pago = 0 
         AND vencimento >= '{$dataIniciosp}'
         AND agrup_destino = 0 
         AND caucao = '0000-00-00'	
         AND inadimplencia = '0000-00-00' 
         AND cartorio = '0000-00-00' 
         AND advogado = '0000-00-00'
         AND cobradora = '0000-00-00' 
         AND correio = '0000-00-00'	
         AND spc_serasa = '0000-00-00'  
         AND cliente IN (84537,9605,70065)
         GROUP BY cliente";
     $resultado_clientes_ammo_details = mysqli_query($conexao, $sql_clientes_ammo_details);

     // Consulta para detalhar o saldo a receber vencidos dos clientes específicos da AMMO
     $sql_clientes_ammo_vencidos = "SELECT cliente, SUM(valor) as saldo_cliente_vencido 
         FROM ".$prefixjrf.".ct_receb 
         WHERE valor_pago = 0 
         AND vencimento BETWEEN '{$dataIniciorve}' AND '{$dataFimrve}'
         AND agrup_destino = 0 
         AND caucao = '0000-00-00'	
         AND inadimplencia = '0000-00-00' 
         AND cartorio = '0000-00-00' 
         AND advogado = '0000-00-00'
         AND cobradora = '0000-00-00' 
         AND correio = '0000-00-00'	
         AND spc_serasa = '0000-00-00'  
         AND cliente IN (84537,9605,70065)
         GROUP BY cliente";
     $resultado_clientes_ammo_vencidos = mysqli_query($conexao, $sql_clientes_ammo_vencidos);

     // Consulta para detalhar o saldo a pagar sem previsões dos fornecedores específicos da AMMO
     $sql_pagar_sem_previsoes_ammo_details = "SELECT fornecedor, SUM(valor) AS saldo_fornecedor
         FROM ".$prefixjrf.".ct_pagar WHERE valor_pago = 0 AND vencimento > CURRENT_DATE() AND agrup_destino = 0 AND provisao = 'N'  
         AND fornecedor IN (392,12888,12156) AND valor > 0.01 GROUP BY fornecedor";
     $resultado_pagar_sem_previsoes_ammo_details = mysqli_query($conexao, $sql_pagar_sem_previsoes_ammo_details);


// Consulta estoque
     $sql13 = "SELECT SUM(custo_estoque*0.97) AS soma_total
     FROM (
        SELECT ROUND(SUM(IF(pc.indexado = 's', (pc.custo_medio * ps.estoque * (i.indice)), (pc.custo_medio * ps.estoque))), 2) AS custo_estoque
         FROM mesquita_estoque.prod_setor ps
         LEFT JOIN mesquita_publico.prod_custos pc ON (pc.produto = ps.produto) AND (pc.filial = 2)
         LEFT JOIN mesquita_publico.cad_prod p ON p.codigo = ps.produto
         INNER JOIN (SELECT indice FROM mesquita_vendas.parametros) i
         WHERE p.tipo IN (1, 7, 8) 
         AND p.ativo = 'S'
         UNION ALL
         SELECT ROUND(SUM(IF(pc.indexado = 's', (pc.custo_medio * ps.estoque * (i.indice)), (pc.custo_medio * ps.estoque))), 2) AS custo_estoque
         FROM jr_estoque.prod_setor ps
         LEFT JOIN jr_publico.prod_custos pc ON (pc.produto = ps.produto) AND (pc.filial = 1)
         LEFT JOIN jr_publico.cad_prod p ON p.codigo = ps.produto
         INNER JOIN (SELECT indice FROM jr_vendas.parametros) i
         WHERE p.tipo IN (1, 7, 8) 
         AND p.ativo = 'S'
     ) AS subconsulta ;";
     $resultado13 = mysqli_query($conexao, $sql13);
     $linha13 = mysqli_fetch_assoc($resultado13);

     $sql14 = "SELECT SUM(custo_estoque) AS soma_total1
     FROM (
        
         SELECT ROUND(SUM(IF(pc.indexado = 's', (pc.custo_medio * ps.estoque * (i.indice)), (pc.custo_medio * ps.estoque))), 2) AS custo_estoque
         FROM jr_estoque.prod_setor ps
         LEFT JOIN jr_publico.prod_custos pc ON (pc.produto = ps.produto) AND (pc.filial = 1)
         LEFT JOIN jr_publico.cad_prod p ON p.codigo = ps.produto
         INNER JOIN (SELECT indice FROM jr_vendas.parametros) i
         WHERE p.tipo IN (1, 7, 8) 
         AND p.ativo = 'S'
     ) AS subconsulta;";
     $resultado14 = mysqli_query($conexao, $sql14);
     $linha14 = mysqli_fetch_assoc($resultado14);


     $sql15 = "SELECT SUM(custo_estoque) AS soma_total2
     FROM (
        SELECT ROUND(SUM(IF(pc.indexado = 's', (pc.custo_medio * ps.estoque * (i.indice)), (pc.custo_medio * ps.estoque))), 2) AS custo_estoque
         FROM mesquita_estoque.prod_setor ps
         LEFT JOIN mesquita_publico.prod_custos pc ON (pc.produto = ps.produto) AND (pc.filial = 2)
         LEFT JOIN mesquita_publico.cad_prod p ON p.codigo = ps.produto
         INNER JOIN (SELECT indice FROM mesquita_vendas.parametros) i
         WHERE p.tipo IN (1, 7, 8)  
         AND p.ativo = 'S'
     ) AS subconsulta;";
     $resultado15 = mysqli_query($conexao, $sql15);
     $linha15 = mysqli_fetch_assoc($resultado15);


     ?>

<div class="dashboard-grid">
    <div class="kpi-row">
        <div class="kpi-card <?php echo $linha6["total_saldo"] >= 0 ? 'positive' : 'negative'; ?>">
            <div class="kpi-label">Saldo em Caixa (Conciliado)</div>
            <div class="kpi-value <?php echo $linha6["total_saldo"] >= 0 ? 'green' : 'red'; ?>">
                R$ <?php echo number_format($linha6["total_saldo"], 2, ",", "."); ?>
            </div>
        </div>
        <div class="kpi-card positive">
            <div class="kpi-label">Patrimônio em Estoque</div>
            <div class="kpi-value green">
                R$ <?php echo number_format($linha13["soma_total"], 2, ",", "."); ?>
            </div>
        </div>
        <div class="kpi-card positive">
            <div class="kpi-label">Pendente a Receber</div>
            <?php 
                    $receberTotal = $linha7["saldorecebergeral"] + $linha8["saldorecebergerala"] + $linha1["valorcartao"] + $linha16["valorcartaoa"] + $linha2["saldoreceber"] + $linha9["saldorecebera"];
                ?>
            <div class="kpi-value">
                R$ <?php echo number_format($receberTotal, 2, ",", "."); ?>
            </div>
        </div>
        <div class="kpi-card negative">
            <div class="kpi-label">Compromissos a Pagar</div>
            <?php 
                    $pagarTotal = $linha3["saldopagar"] + $linha4["saldopagardia"] + $linha5["provisoes"] + $linha10["saldopagara"] + $linha11["saldopagardiaa"] + $linha12["provisoesa"];
                ?>
            <div class="kpi-value red">
                R$ <?php echo number_format($pagarTotal, 2, ",", "."); ?>
            </div>
        </div>
        <div
            class="kpi-card <?php echo ($receberTotal + $linha6["total_saldo"] + $linha13["soma_total"] - $pagarTotal) >= 0 ? 'positive' : 'negative'; ?>">
            <div class="kpi-label">Balanço Projetado + Estoque</div>
            <div class="kpi-value">
                R$
                <?php echo number_format($receberTotal + $linha6["total_saldo"] + $linha13["soma_total"] - $pagarTotal, 2, ",", "."); ?>
            </div>
        </div>
    </div>

    <div class="chart-container">
        <div class="kpi-label" style="margin-bottom: 5px;">Composição Financeira</div>
        <canvas id="balanceChart" width="220" height="220"></canvas>
    </div>
</div>

<div class="table-container">
    <table id="tabela">
        <thead>
            <tr>
                <th>Fluxo Detalhado - SYMA + AMMO</th>
                <th style="text-align: right;">Valores (BRL)</th>
            </tr>
        </thead>
        <tbody>
            <?php $total = 0;?>
            <tr>
                <td>Saldo do dia (Conciliado)</td>
                <td class="mono <?php echo $linha6["total_saldo"] >= 0 ? 'text-positive' : 'text-negative'; ?>"
                    style="text-align: right;">
                    R$ <?php echo number_format($linha6["total_saldo"], 2, ",", "."); ?>
                </td>
                <?php $total += $linha6["total_saldo"];?>
            </tr>
            <tr>
                <td>Saldo Estoque AMMO</td>
                <td class="mono" style="text-align: right;">
                    R$ <?php echo number_format($linha14["soma_total1"], 2, ",", "."); ?>
                </td>
            </tr>
            <tr>
                <td>Saldo Estoque SYMA</td>
                <td class="mono" style="text-align: right;">
                    R$ <?php echo number_format($linha15["soma_total2"], 2, ",", "."); ?>
                </td>
            </tr>
            <tr style="background: rgba(0, 255, 136, 0.05);">
                <td style="font-weight: bold;">TOTAL ESTOQUE (PATRIMÔNIO)</td>
                <td class="mono text-positive" style="text-align: right; font-weight: bold;">
                    R$ <?php echo number_format($linha13["soma_total"], 2, ",", "."); ?>
                </td>
                <?php $total += $linha13["soma_total"];?>
            </tr>

            <!-- SYMA RECEIVABLES -->
            <tr>
                <td>
                    <span id="toggle-syma-details" class="expand-btn">[+]</span>
                    Saldo a receber SYMA
                </td>
                <td class="mono" style="text-align: right;">R$
                    <?php echo number_format($linha7["saldorecebergeral"], 2, ",", ".");?></td>
                <?php $total += $linha7["saldorecebergeral"];?>
            </tr>
            <?php mysqli_data_seek($resultado_clientes_syma_details, 0); ?>
            <?php while ($detalhe = mysqli_fetch_assoc($resultado_clientes_syma_details)): ?>
            <tr class="detalhes-syma"
                style="display: none; color: var(--text-dim); background: rgba(14, 165, 233, 0.03);">
                <td style="padding-left: 40px; font-size: 0.8rem;">- Cliente ID: <?php echo $detalhe['cliente']; ?></td>
                <td class="mono" style="text-align: right; font-size: 0.8rem;">R$
                    <?php echo number_format($detalhe['saldo_cliente'], 2, ",", "."); ?></td>
            </tr>
            <?php endwhile; ?>

            <!-- SYMA OVERDUE -->
            <tr>
                <td>
                    <span id="toggle-receber-vencidos-details" class="expand-btn">[+]</span>
                    Saldo a receber vencidos SYMA
                </td>
                <td class="mono" style="text-align: right;">R$
                    <?php echo number_format($linha2["saldoreceber"], 2, ",", ".");?></td>
                <?php $total += $linha2["saldoreceber"];?>
            </tr>
            <?php mysqli_data_seek($resultado_clientes_syma_vencidos, 0); ?>
            <?php while ($detalhe = mysqli_fetch_assoc($resultado_clientes_syma_vencidos)): ?>
            <tr class="detalhes-receber-vencidos"
                style="display: none; color: var(--text-dim); background: rgba(14, 165, 233, 0.03);">
                <td style="padding-left: 40px; font-size: 0.8rem;">- Cliente ID: <?php echo $detalhe['cliente']; ?></td>
                <td class="mono" style="text-align: right; font-size: 0.8rem;">R$
                    <?php echo number_format($detalhe['saldo_cliente_vencido'], 2, ",", "."); ?></td>
            </tr>
            <?php endwhile; ?>

            <tr>
                <td>Saldo a receber cartões SYMA</td>
                <td class="mono" style="text-align: right;">R$
                    <?php echo number_format($linha1["valorcartao"], 2, ",", ".");?></td>
                <?php $total += $linha1["valorcartao"];?>
            </tr>

            <!-- SYMA PAYABLES -->
            <tr style="background: rgba(255, 62, 62, 0.05);">
                <td>
                    <span id="toggle-pagar-sem-previsoes-details" class="expand-btn">[+]</span>
                    Saldo a pagar sem previsões SYMA
                </td>
                <td class="mono text-negative" style="text-align: right;">R$
                    <?php echo number_format($linha3["saldopagar"], 2, ",", ".");?></td>
                <?php $total -= $linha3["saldopagar"];?>
            </tr>
            <?php mysqli_data_seek($resultado_pagar_sem_previsoes_details, 0); ?>
            <?php while ($detalhe = mysqli_fetch_assoc($resultado_pagar_sem_previsoes_details)): ?>
            <tr class="detalhes-pagar-sem-previsoes"
                style="display: none; color: var(--text-dim); background: rgba(255, 62, 62, 0.03);">
                <td style="padding-left: 40px; font-size: 0.8rem;">- Fornecedor ID:
                    <?php echo $detalhe['fornecedor']; ?></td>
                <td class="mono" style="text-align: right; font-size: 0.8rem;">R$
                    <?php echo number_format($detalhe['saldo_fornecedor'], 2, ",", "."); ?></td>
            </tr>
            <?php endwhile; ?>

            <tr style="background: rgba(255, 62, 62, 0.05);">
                <td>Saldo a pagar do dia SYMA</td>
                <td class="mono text-negative" style="text-align: right;">R$
                    <?php echo number_format($linha4["saldopagardia"], 2, ",", ".");?></td>
                <?php $total -= $linha4["saldopagardia"];?>
            </tr>

            <tr style="background: rgba(255, 62, 62, 0.05);">
                <td>Provisões a pagar (30 dias) SYMA</td>
                <td class="mono text-negative" style="text-align: right;">R$
                    <?php echo number_format($linha5["provisoes"], 2, ",", ".");?></td>
                <?php $total -= $linha5["provisoes"];?>
            </tr>

            <tr style="cursor: pointer;"
                onclick="window.open('provisoesantigas_analitico.php?data=<?php echo urlencode($dataIniciopro); ?>', '_blank')">
                <td style="color: var(--accent-blue);">Provisões a pagar (Antigas) SYMA ↗</td>
                <td class="mono" style="text-align: right;">R$
                    <?php echo number_format($linhaproantigo["provisoesantigas"], 2, ",", "."); ?></td>
            </tr>

            <!-- AMMO SECTION -->
            <tr>
                <td>
                    <span id="toggle-ammo-details" class="expand-btn">[+]</span>
                    Saldo a receber AMMO
                </td>
                <td class="mono text-positive" style="text-align: right;">R$
                    <?php echo number_format($linha8["saldorecebergerala"], 2, ",", ".");?></td>
                <?php $total += $linha8["saldorecebergerala"];?>
            </tr>
            <?php mysqli_data_seek($resultado_clientes_ammo_details, 0); ?>
            <?php while ($detalhe = mysqli_fetch_assoc($resultado_clientes_ammo_details)): ?>
            <tr class="detalhes-ammo"
                style="display: none; color: var(--text-dim); background: rgba(14, 165, 233, 0.03);">
                <td style="padding-left: 40px; font-size: 0.8rem;">- Cliente ID: <?php echo $detalhe['cliente']; ?></td>
                <td class="mono" style="text-align: right; font-size: 0.8rem;">R$
                    <?php echo number_format($detalhe['saldo_cliente'], 2, ",", "."); ?></td>
            </tr>
            <?php endwhile; ?>

            <tr>
                <td>
                    <span id="toggle-receber-vencidos-ammo-details" class="expand-btn">[+]</span>
                    Saldo a receber vencidos AMMO
                </td>
                <td class="mono text-positive" style="text-align: right;">R$
                    <?php echo number_format($linha9["saldorecebera"], 2, ",", ".");?></td>
                <?php $total += $linha9["saldorecebera"];?>
            </tr>
            <?php mysqli_data_seek($resultado_clientes_ammo_vencidos, 0); ?>
            <?php while ($detalhe = mysqli_fetch_assoc($resultado_clientes_ammo_vencidos)): ?>
            <tr class="detalhes-receber-vencidos-ammo"
                style="display: none; color: var(--text-dim); background: rgba(14, 165, 233, 0.03);">
                <td style="padding-left: 40px; font-size: 0.8rem;">- Cliente ID: <?php echo $detalhe['cliente']; ?></td>
                <td class="mono" style="text-align: right; font-size: 0.8rem;">R$
                    <?php echo number_format($detalhe['saldo_cliente_vencido'], 2, ",", "."); ?></td>
            </tr>
            <?php endwhile; ?>

            <tr>
                <td>Saldo a receber cartões AMMO</td>
                <td class="mono" style="text-align: right;">R$
                    <?php echo number_format($linha16["valorcartaoa"], 2, ",", ".");?></td>
                <?php $total += $linha16["valorcartaoa"];?>
            </tr>
            <tr style="background: rgba(255, 62, 62, 0.05);">
                <td>
                    <span id="toggle-pagar-sem-previsoes-ammo-details" class="expand-btn">[+]</span>
                    Saldo a pagar sem previsões AMMO
                </td>
                <td class="mono text-negative" style="text-align: right;">R$
                    <?php echo number_format($linha10["saldopagara"], 2, ",", ".");?></td>
                <?php $total -= $linha10["saldopagara"];?>
            </tr>
            <?php mysqli_data_seek($resultado_pagar_sem_previsoes_ammo_details, 0); ?>
            <?php while ($detalhe = mysqli_fetch_assoc($resultado_pagar_sem_previsoes_ammo_details)): ?>
            <tr class="detalhes-pagar-sem-previsoes-ammo"
                style="display: none; color: var(--text-dim); background: rgba(255, 62, 62, 0.03);">
                <td style="padding-left: 40px; font-size: 0.8rem;">- Fornecedor ID:
                    <?php echo $detalhe['fornecedor']; ?></td>
                <td class="mono" style="text-align: right; font-size: 0.8rem;">R$
                    <?php echo number_format($detalhe['saldo_fornecedor'], 2, ",", "."); ?></td>
            </tr>
            <?php endwhile; ?>
            <tr style="background: rgba(255, 62, 62, 0.05);">
                <td>Saldo a pagar do dia AMMO</td>
                <td class="mono text-negative" style="text-align: right;">R$
                    <?php echo number_format($linha11["saldopagardiaa"], 2, ",", ".");?></td>
                <?php $total -= $linha11["saldopagardiaa"];?>
            </tr>
            <tr style="background: rgba(255, 62, 62, 0.05);">
                <td>Provisões a pagar (30 dias) AMMO</td>
                <td class="mono text-negative" style="text-align: right;">R$
                    <?php echo number_format($linha12["provisoesa"], 2, ",", ".");?></td>
                <?php $total -= $linha12["provisoesa"];?>
            </tr>

            <tr style="background: #1a1a1c;">
                <td style="font-weight: 700; font-size: 1.1rem; color: var(--accent-green);">BALANÇO TOTAL ESTIMADO</td>
                <td class="mono <?php echo $total >= 0 ? 'text-positive' : 'text-negative'; ?>"
                    style="text-align: right; font-weight: 700; font-size: 1.1rem;">
                    R$ <?php echo number_format($total, 2, ",", ".");?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="action-panel">
    <button type="button" class="btn-action" onclick="toggleAllDetails()">Expandir Tudo [+]</button>
    <button type="button" class="btn-action" onclick="copyResult()">Copiar Dados</button>
    <button type="button" class="btn-action success" onclick="exportarParaXLSX()">Exportar Excel</button>
    <button type="button" class="btn-action" onclick="exportarParaPDF()">Exportar PDF</button>
    <button type="button" class="btn-action" onclick="window.print()">Imprimir HUD</button>
</div>

<?php
    if (isset($_POST["update-button"])) {
        echo "<div id='processing-message'>Processando...</div>";
        echo "<script>window.onload = function() { document.getElementById('processing-message').style.display = 'none'; };</script>";
    }
    endif;
    ?>

<script>
function exportarParaXLSX() {
    // Cria um novo workbook
    var wb = XLSX.utils.book_new();

    // Define o título
    var titulo = "Relatório de Fluxo Geral - SYMA + AMMO";

    // Adiciona o título manualmente na primeira linha
    var ws_data = [
        [titulo], // Título na primeira linha
        [], // Linha vazia para espaçamento
    ];

    // Converte a tabela HTML em formato de array
    var tabela = XLSX.utils.table_to_sheet(document.getElementById('tabela'));
    var range = XLSX.utils.decode_range(tabela['!ref']);

    // Adiciona os dados da tabela após o título
    for (var R = range.s.r; R <= range.e.r; ++R) {
        var row = [];
        for (var C = range.s.c; C <= range.e.c; ++C) {
            var cell = tabela[XLSX.utils.encode_cell({
                r: R,
                c: C
            })];
            row.push(cell ? cell.v : "");
        }
        ws_data.push(row);
    }

    // Cria uma nova planilha com os dados do título + tabela
    var ws = XLSX.utils.aoa_to_sheet(ws_data);

    // Define a largura das colunas
    ws['!cols'] = [{
        wch: 30
    }, {
        wch: 20
    }];

    // Mescla as células da primeira linha para o título ocupar toda a largura
    ws['!merges'] = [{
        s: {
            r: 0,
            c: 0
        },
        e: {
            r: 0,
            c: 1
        }
    }];

    // Aplica o estilo para a célula do título
    ws['A1'].s = {
        font: {
            sz: 36,
            bold: true
        },
        alignment: {
            horizontal: "center",
            vertical: "center"
        }
    };

    // Adiciona a planilha ao workbook
    XLSX.utils.book_append_sheet(wb, ws, "Fluxo");

    // Gera o arquivo Excel e faz o download
    var wbout = XLSX.write(wb, {
        bookType: 'xlsx',
        type: 'binary'
    });

    function s2ab(s) {
        var buf = new ArrayBuffer(s.length);
        var view = new Uint8Array(buf);
        for (var i = 0; i < s.length; i++) view[i] = s.charCodeAt(i) & 0xFF;
        return buf;
    }

    // Cria um link de download
    var link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([s2ab(wbout)], {
        type: "application/octet-stream"
    }));
    link.download = 'fluxo_geral.xlsx';
    link.click();
}

function copyResult() {
    var tabela = document.getElementById('tabela');
    var el = document.createElement('textarea');
    el.value = tabela.innerText;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    alert('Resultado copiado para a área de transferência!');
}

function exportarParaPDF() {
    const {
        jsPDF
    } = window.jspdf;
    const doc = new jsPDF();

    // Adiciona o título com tamanho da fonte 36, centralizado e em negrito
    var titulo = "Relatório de Fluxo Geral - SYMA + AMMO";
    doc.setFontSize(24);
    doc.setFont("helvetica", "bold");
    doc.text(titulo, doc.internal.pageSize.getWidth() / 2, 20, {
        align: "center"
    });

    // Adiciona a tabela abaixo do título usando autoTable
    doc.autoTable({
        html: '#tabela',
        startY: 40, // Inicia abaixo do título
        styles: {
            fontSize: 12, // Tamanho da fonte da tabela
            cellPadding: 3,
        },
        headStyles: {
            fillColor: [220, 220, 220] // Cor de fundo para o cabeçalho
        }
    });

    // Gera e baixa o PDF
    doc.save('fluxo_geral.pdf');
}

// --- NOVO CÓDIGO INICIA AQUI ---
// Adiciona o listener de clique para o novo botão de toggle
document.addEventListener('DOMContentLoaded', (event) => {
    const toggleButton = document.getElementById('toggle-syma-details');
    if (toggleButton) {
        toggleButton.addEventListener('click', function() {
            const rows = document.querySelectorAll('.detalhes-syma');
            // Verifica se a primeira linha de detalhe está oculta para decidir a ação
            const isHidden = rows.length > 0 && rows[0].style.display === 'none';

            // Alterna a visibilidade de todas as linhas de detalhe
            rows.forEach(row => {
                row.style.display = isHidden ? 'table-row' : 'none';
            });

            // Muda o ícone do botão
            this.textContent = isHidden ? '[-]' : '[+]';
        });
    }
});
const toggleButtonPagar = document.getElementById('toggle-pagar-sem-previsoes-details');
if (toggleButtonPagar) {
    toggleButtonPagar.addEventListener('click', function() {
        const rows = document.querySelectorAll('.detalhes-pagar-sem-previsoes');
        const isHidden = rows.length > 0 && rows[0].style.display === 'none';

        rows.forEach(row => {
            row.style.display = isHidden ? 'table-row' : 'none';
        });

        this.textContent = isHidden ? '[-]' : '[+]';
    });
}

const toggleButtonrecebervencidos = document.getElementById('toggle-receber-vencidos-details');
if (toggleButtonrecebervencidos) {
    toggleButtonrecebervencidos.addEventListener('click', function() {
        const rows = document.querySelectorAll('.detalhes-receber-vencidos');
        const isHidden = rows.length > 0 && rows[0].style.display === 'none';

        rows.forEach(row => {
            row.style.display = isHidden ? 'table-row' : 'none';
        });

        this.textContent = isHidden ? '[-]' : '[+]';
    });
}

const toggleButtonAmmo = document.getElementById('toggle-ammo-details');
if (toggleButtonAmmo) {
    toggleButtonAmmo.addEventListener('click', function() {
        const rows = document.querySelectorAll('.detalhes-ammo');
        const isHidden = rows.length > 0 && rows[0].style.display === 'none';
        rows.forEach(row => row.style.display = isHidden ? 'table-row' : 'none');
        this.textContent = isHidden ? '[-]' : '[+]';
    });
}

const toggleButtonVencidosAmmo = document.getElementById('toggle-receber-vencidos-ammo-details');
if (toggleButtonVencidosAmmo) {
    toggleButtonVencidosAmmo.addEventListener('click', function() {
        const rows = document.querySelectorAll('.detalhes-receber-vencidos-ammo');
        const isHidden = rows.length > 0 && rows[0].style.display === 'none';
        rows.forEach(row => row.style.display = isHidden ? 'table-row' : 'none');
        this.textContent = isHidden ? '[-]' : '[+]';
    });
}

const toggleButtonPagarAmmo = document.getElementById('toggle-pagar-sem-previsoes-ammo-details');
if (toggleButtonPagarAmmo) {
    toggleButtonPagarAmmo.addEventListener('click', function() {
        const rows = document.querySelectorAll('.detalhes-pagar-sem-previsoes-ammo');
        const isHidden = rows.length > 0 && rows[0].style.display === 'none';
        rows.forEach(row => row.style.display = isHidden ? 'table-row' : 'none');
        this.textContent = isHidden ? '[-]' : '[+]';
    });
}

// --- FUNCTIONAL UPDATES ---
function filterTable() {
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("table-search");
    filter = input.value.toUpperCase();
    table = document.getElementById("tabela");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) { // Skip header
        // Skip detail rows during filtering - only filter main rows
        if (tr[i].className.includes('detalhes-')) continue;

        td = tr[i].getElementsByTagName("td")[0];
        if (td) {
            txtValue = td.textContent || td.innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}

let allExpanded = false;

function toggleAllDetails() {
    allExpanded = !allExpanded;
    const buttons = document.querySelectorAll('.expand-btn');
    const allDetailRows = document.querySelectorAll('[class*="detalhes-"]');

    allDetailRows.forEach(row => {
        row.style.display = allExpanded ? 'table-row' : 'none';
    });

    buttons.forEach(btn => {
        btn.textContent = allExpanded ? '[-]' : '[+]';
    });

    // Update trigger button
    const controlBtn = document.querySelector('button[onclick="toggleAllDetails()"]');
    controlBtn.textContent = allExpanded ? 'Recolher Tudo [-]' : 'Expandir Tudo [+]';
}

// Interactive KPIs
document.querySelectorAll('.kpi-card').forEach(card => {
    card.style.cursor = 'pointer';
    card.addEventListener('click', () => {
        const label = card.querySelector('.kpi-label').innerText.toUpperCase();
        const searchBox = document.getElementById('table-search');

        if (label.includes('ESTOQUE')) searchBox.value = 'ESTOQUE';
        else if (label.includes('RECEBER')) searchBox.value = 'RECEBER';
        else if (label.includes('PAGAR')) searchBox.value = 'PAGAR';
        else if (label.includes('CAIXA')) searchBox.value = 'SALDO DO DIA';

        filterTable();
        document.querySelector('.table-container').scrollIntoView({
            behavior: 'smooth'
        });
    });
});

// Chart Core
const ctx = document.getElementById('balanceChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Caixa', 'Estoque', 'Recebíveis', 'Obrigações'],
        datasets: [{
            data: [
                <?php echo $linha6["total_saldo"]; ?>,
                <?php echo $linha13["soma_total"]; ?>,
                <?php echo $receberTotal; ?>,
                <?php echo $pagarTotal; ?>
            ],
            backgroundColor: ['#0ea5e9', '#00ff88', '#f6c23e', '#ff3e3e'],
            borderWidth: 0,
            hoverOffset: 15
        }]
    },
    options: {
        cutout: '75%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: '#94a3b8',
                    font: {
                        family: 'Inter',
                        size: 10
                    }
                }
            }
        }
    }
});
</script>
</nav>
</body>

</html>