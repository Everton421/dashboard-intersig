 <nav class="mt-2">
     <!--begin::Sidebar Menu-->
     <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="Main navigation"
         data-accordion="false" id="navigation">
         <li class="nav-item menu-open">
             <a href="#" class="nav-link active">
                 <i class="nav-icon bi bi-speedometer text-warning"></i>
                 <p>
                     Dashboard
                     <i class="nav-arrow bi bi-chevron-right"></i>
                 </p>
             </a>
             <ul class="nav nav-treeview">
                 <li class="nav-item">
                     <a href="../dashboard/dashboard.php" class="nav-link active">
                         <i class="nav-icon bi bi-circle"></i>
                         <p>Dashboard</p>
                     </a>
                 </li>
                  <li class="nav-item">
                     <a href="../dashboard/dashboard_financeiro.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-danger"></i>
                         <p>Financeiro</p>
                     </a>
                 </li>

                 <li class="nav-item">
                     <a href="../dashboard/dashboard_compras.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-danger"></i>
                         <p>Compras</p>
                     </a>
                 </li>


             </ul>
         </li>
            <li class="nav-item">
             <a href="#" class="nav-link">
                 <i class="bi bi-file-earmark-check text-primary"></i>
                 <p>
                     Pedidos
                     <i class="nav-arrow bi bi-chevron-right"></i>
                 </p>
             </a>
             <ul class="nav nav-treeview">
                 <li class="nav-item">
                     <a href="../pedidos/resumo_pedidos.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-danger"></i>
                         <p>Vendas</p>
                     </a>
                 </li>
                 <li class="nav-item">
                     <a href="../pedidos/resumo_pedidosc.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-danger"></i>
                         <p>Compras</p>
                     </a>
                 </li>

             </ul>
         </li>

         <li class="nav-item">
             <a href="#" class="nav-link">
                 <i class="bi bi-file-earmark-check text-primary"></i>
                 <p>
                     Documentos
                     <i class="nav-arrow bi bi-chevron-right"></i>
                 </p>
             </a>
             <ul class="nav nav-treeview">
                 <li class="nav-item">
                     <a href="../documentos/entradas.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-danger"></i>
                         <p>Entradas</p>
                     </a>

                     <a href="../documentos/saidas.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-info"></i>
                         <p>Saídas</p>
                     </a>
                 </li>

             </ul>
         </li>

           <li class="nav-item">
             <a href="#" class="nav-link">
                <i class="bi bi-cash-coin text-success"></i>
                 <p>
                     Financeiro
                     <i class="nav-arrow bi bi-chevron-right"></i>
                 </p>
             </a>
             <ul class="nav nav-treeview">
                 <li class="nav-item">
                     <a href="../financeiro/receber.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-danger"></i>
                         <p>Receber</p>
                     </a>

                     <a href="../financeiro/pagar.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-info"></i>
                         <p>Pagar</p>
                     </a>
                   



                 </li>

             </ul>
         </li>
         <li class="nav-item">
             <a href="#" class="nav-link">
                 <i class="bi bi-bar-chart-line-fill text-success"></i>
                 <p>
                     Relatórios
                     <span class="nav-badge badge text-bg-secondary me-3"></span>
                     <i class="nav-arrow bi bi-chevron-right"></i>
                 </p>
             </a>
             <ul class="nav nav-treeview">
                 <li class="nav-item">
                     <a href="../relatorios/resumo_cfop.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-primary"></i>
                         <p>Resumo Por CFOP</p>
                     </a>
                 </li>

                 <li class="nav-item">
                     <a href="../relatorios/resumo_ipi.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-warning"></i>
                         <p>Resumo Por IPI</p>
                     </a>
                 </li>

                 <li class="nav-item">
                     <a href="../relatorios/resumo_pis.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-info"></i>
                         <p>Resumo Pis</p>
                     </a>
                 </li>
                 <li class="nav-item">
                     <a href="../relatorios/resumo_cofins.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-secondary"></i>
                         <p>Resumo Cofins</p>
                     </a>
                 </li>

                 <li class="nav-item">
                     <a href="../relatorios/consulta_doc_fiscais.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-warning"></i>
                         <p>Doc. Fiscais Cst</p>
                     </a>
                 </li>

             </ul>
         </li>
         <li class="nav-item">
             <a href="#" class="nav-link">
                 <i class="bi bi-person-vcard text-danger"></i>
                 <p>
                     Cadastros
                     <i class="nav-arrow bi bi-chevron-right"></i>
                 </p>
             </a>
             <ul class="nav nav-treeview">
                 <li class="nav-item">
                     <a href="../cadastros/clientes_view.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-success"></i>
                         <p>Clientes</p>
                     </a>
                 </li>
                 <li class="nav-item">
                     <a href="../cadastros/fornecedores_view.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-primary"></i>
                         <p>Fornecedores</p>
                     </a>
                 </li>
                 <li class="nav-item">
                     <a href="../cadastros/produtos_view.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-info"></i>
                         <p>Produtos</p>
                     </a>
                 </li>
                 <li class="nav-item">
                     <a href="../series/series.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-info"></i>
                         <p>Séries</p>
                     </a>
                 </li>
                 <li class="nav-item">
                     <a href="../transacoes/transacoes.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-secondary"></i>
                         <p>Transação</p>
                     </a>
                 </li>
                 <li class="nav-item">
                     <a href="../cadastros/empresas_view.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-light"></i>
                         <p>Empresa</p>
                     </a>
                 </li>

                 <li class="nav-item">
                     <a href="../usuario/usuarios.php" class="nav-link">
                         <i class="nav-icon bi bi-circle text-light"></i>
                         <p>Usuarios</p>
                     </a>
                 </li>
             </ul>
         </li>

     </ul>
     <!--end::Sidebar Menu-->
 </nav>