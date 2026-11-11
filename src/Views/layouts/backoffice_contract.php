<?php
/** Contrato único de apresentação dos quatro backoffices autenticados. */
return [
    'admin' => ['preset' => 'operator', 'workspace' => ['Central GuinchaFácil', 'Workspace operacional'], 'home' => '/admin/central'],
    'funcionario' => ['preset' => 'finance', 'workspace' => ['Atendimento', 'Solicitações e demandas'], 'home' => '/funcionario/dashboard'],
    'gerente' => ['preset' => 'executive', 'workspace' => ['Gerência', 'Aprovações e alçadas'], 'home' => '/gerente/dashboard'],
    'guincho' => ['preset' => 'driver', 'workspace' => ['Guincho parceiro', 'Operação do prestador'], 'home' => '/guincho/dashboard'],
    'oficina' => ['preset' => 'workshop', 'workspace' => ['Oficina parceira', 'Pátio e orçamentos'], 'home' => '/oficina/dashboard'],
    'cliente' => ['preset' => 'client', 'workspace' => ['Área do cliente', 'Minhas solicitações'], 'home' => '/cliente/dashboard'],
    'especialista' => ['preset' => 'driver', 'workspace' => ['Especialista', 'Atendimento no local'], 'home' => '/especialista/dashboard'],
    'parceiro' => ['preset' => 'operator', 'workspace' => ['Parceiro', 'Indicações'], 'home' => '/parceiro/dashboard'],
];
