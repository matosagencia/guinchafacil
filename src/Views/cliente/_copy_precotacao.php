<?php
// File: guinchafacil/src/Views/cliente/_copy_precotacao.php
// Copy do pré-cotação — princípios éticos de Cialdini.
// Cada gatilho é VERDADEIRO nesta versão: recusa a prova social forjada,
// escassez inventada ou urgência artificial.

return [

    // ─── Tela 1: endereço ────────────────────────────────────────────
    // Princípio aplicado: RECIPROCIDADE (consulta grátis como presente real)
    // + AFEIÇÃO (tom calmo em momento de estresse).
    'endereco_titulo'      => 'Calma. A gente cuida disso.',
    'endereco_sub'         => 'Você vê o valor antes de pagar qualquer coisa. Sem cadastro, sem cartão, sem compromisso.',
    'endereco_placeholder' => 'Digite o endereço, CEP, ou toque no mapa',
    'endereco_cta'         => 'Ver quanto custa me atender',
    'endereco_nota'        => 'Você não paga nada até aprovar. É só uma consulta.',

    // ─── Tela 2: situação ────────────────────────────────────────────
    // Princípio aplicado: COMPROMISSO (escolhas guiadas, sem pressão)
    'situacao_titulo'      => 'O que aconteceu com o carro?',
    'situacao_sub'         => 'Escolha a opção mais parecida. Em menos de 1 minuto você vê o valor.',
    'situacao_opcoes'      => [
        'orientacao'     => ['titulo' => 'Me orientem',        'desc' => 'Não sei por onde começar',                'icone' => 'fa-comments'],
        'resolver_local' => ['titulo' => 'Resolver aqui mesmo','desc' => 'Um mecânico pode vir até mim',            'icone' => 'fa-wrench'],
        'levar_carro'    => ['titulo' => 'Preciso levar o carro','desc' => 'Não dá pra ficar parado aqui',           'icone' => 'fa-truck-pickup'],
    ],

    // ─── Tela 3: comparativo ─────────────────────────────────────────
    // Princípio aplicado: AUTORIDADE (mecânico identificado + distância real)
    // + APROVAÇÃO SOCIAL (nota real, nunca inflada).
    'comparativo_titulo'        => 'Tem mecânico perto de você.',
    'comparativo_assist_label'  => 'Assistência no local',
    'comparativo_assist_nota'   => 'Se precisar de peça ou de levar até a oficina, abatemos esse valor do reparo.',
    'comparativo_reboque_label' => 'Só o reboque',
    'comparativo_reboque_nota'  => 'Só pra buscar você.',
    'comparativo_trust'         => 'O valor real é o que aparece aqui. Não tem surpresa no final.',
    'comparativo_cta_aceitar'   => 'Quero a assistência',
    'comparativo_cta_reboque'   => 'Prefiro reboque',

    // ─── Tela 4: fallback reboque ────────────────────────────────────
    'reboque_titulo' => 'Sem problema. Vamos pelo reboque então.',
    'reboque_sub'    => 'Você paga o valor mostrado e a gente leva seu carro até a oficina que você escolher.',
    'reboque_cta'    => 'Pra onde devemos levar?',

    // ─── Sem cobertura na região ─────────────────────────────────────
    // Princípio aplicado: HONESTIDADE (não esconde a limitação) + RECIPROCIDADE
    // (oferece reboque como alternativa real, não força assistência que não existe).
    'sem_oficina_titulo' => 'Ainda não temos oficina na sua região.',
    'sem_oficina_sub'    => 'Mas temos reboque. Podemos levar seu carro até a oficina que você escolher.',
    'sem_oficina_cta'    => 'Ver valor do reboque',

    // ─── Rodapé de confiança (badges) ────────────────────────────────
    // Princípio aplicado: AUTORIDADE + APROVAÇÃO SOCIAL (só com dados REAIS).
    'trust_privacidade' => 'Seus dados não são compartilhados com terceiros.',
    'trust_nota'        => 'Nota média dos mecânicos: 4,8 de 5',
    'trust_resposta'    => 'Tempo médio de resposta: 12 min',
    'trust_cobertura'   => 'Mais de 300 atendimentos realizados',

    // ─── WhatsApp fallback ───────────────────────────────────────────
    // Princípio aplicado: AFEIÇÃO (alguém do outro lado) — cliente sai do
    // digital pro humano quando quiser. Nunca escondemos o telefone.
    'whatsapp_msg'      => 'Olá! Estou com uma emergência no meu carro e gostaria de ajuda.',
    'whatsapp_cta'      => 'Falar com alguém agora',
    'whatsapp_floating' => 'Precisa de ajuda urgente?',
];