<?php
// File: guinchafacil/src/Views/cliente/_copy_precotacao.php
// Copy do pré-cotação — princípios éticos de Cialdini (autoridade,
// reciprocidade, prova social, escassez, compromisso).

return [

    // ─── Tela 1: endereço ───
    'endereco_titulo'      => 'Calma. A gente cuida disso.',
    'endereco_sub'         => 'Você vê o valor antes de pagar qualquer coisa. Sem cadastro, sem cartão, sem compromisso.',
    'endereco_placeholder' => 'Digite o endereço, CEP, ou toque no mapa',
    'endereco_cta'         => 'Ver quanto custa me atender',
    'endereco_nota'        => 'Você não paga nada até aprovar. É só uma consulta.',

    // ─── Tela 2: situação ───
    'situacao_titulo'      => 'O que aconteceu com o carro?',
    'situacao_sub'         => 'Escolha a opção mais parecida. Em menos de 1 minuto você vê o valor.',
    'situacao_opcoes'      => [
        'orientacao'     => ['titulo' => 'Me orientem',        'desc' => 'Não sei por onde começar',                'icone' => 'fa-comments'],
        'resolver_local' => ['titulo' => 'Resolver aqui mesmo','desc' => 'Um mecânico pode vir até mim',            'icone' => 'fa-wrench'],
        'levar_carro'    => ['titulo' => 'Preciso levar o carro','desc' => 'Não dá pra ficar parado aqui',           'icone' => 'fa-truck-pickup'],
    ],

    // ─── Tela 3: card A — Assistência Local (Cialdini) ───
    'assist_icone_svg'   => 'mechanic',
    'assist_titulo'      => 'Resolver no local',
    'assist_subtitulo'   => 'Um mecânico profissional vai até onde você está',
    'assist_descricao'   => 'Em poucos minutos, um mecânico verificado avalia seu veículo e tenta resolver ali mesmo. Se precisar de peças, você paga a diferença direto para ele, sem intermediários.',
    'assist_beneficio'   => 'Se ele não conseguir consertar no local, ele mesmo reboca seu carro até a oficina dele — e o valor da assistência é abatido do conserto.',
    'assist_reforco'     => 'Uma escolha inteligente: só para tirar um guincho do lugar custa a partir de R$ 150, fora o trajeto completo. Com a assistência local, você paga bem menos e ainda tem o abatimento.',
    'assist_cta'         => 'Quero resolver no local',

    // ─── Tela 3: card B — Reboque (sem preço inicial) ───
    'reboque_icone_svg'  => 'tow_truck',
    'reboque_titulo'     => 'Rebocar',
    'reboque_subtitulo'  => 'Seu veículo será removido com total segurança',
    'reboque_descricao'  => 'Um guincho plataforma pode ser deslocado até você para remover o veículo até o endereço que você informar — oficina, casa ou qualquer outro destino.',
    'reboque_reforco'    => 'O valor será calculado com base no trajeto completo (origem → destino), sem surpresas.',
    'reboque_cta'        => 'Quero rebocar',

    // ─── Tela 4: fallback reboque ───
    'reboque_fallback_titulo' => 'Sem assistência na sua região',
    'reboque_fallback_sub'    => 'Mas a gente já resolve: um guincho leva seu carro até a oficina que você escolher.',
    'reboque_fallback_cta'    => 'Informar destino',

    // ─── Sem oficina ───
    'sem_oficina_titulo' => 'Sem assistência na sua região',
    'sem_oficina_sub'    => 'Mas a gente resolve: um guincho leva seu carro até a oficina que você escolher. É só informar o destino.',
    'sem_oficina_cta'    => 'Ver valor do reboque',

    // ─── Trust badges ───
    'trust_privacidade' => 'Seus dados não são compartilhados com terceiros.',
    'trust_nota'        => 'Nota média dos mecânicos: 4,8 de 5',
    'trust_resposta'    => 'Tempo médio de resposta: 12 min',
    'trust_cobertura'   => 'Mais de 300 atendimentos realizados',

    // ─── WhatsApp ───
    'whatsapp_msg'      => 'Olá! Estou com uma emergência no meu carro e gostaria de ajuda.',
    'whatsapp_cta'      => 'Falar com alguém agora',
    'whatsapp_floating' => 'Precisa de ajuda urgente?',
];