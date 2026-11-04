<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/MarketingEventService.php';

final class SeoMarketingController extends BaseController
{
    public function servico(string $slug): void
    {
        $catalogo = [
            'guincho-carro' => ['Guincho para carro', 'Para pane, acidente ou imobilização do veículo.', 'Informe sua localização, veja a estimativa e acompanhe o atendimento.', false],
            'guincho-moto' => ['Guincho para moto', 'Assistência para transportar sua moto com segurança.', 'Informe onde a moto está e veja as opções disponíveis.', false],
            'reboque-para-oficina' => ['Reboque para oficina', 'Leve o veículo até a oficina ou destino escolhido.', 'O destino é usado para calcular rota e valor estimado.', true],
            'bateria' => ['Socorro de bateria', 'Ajuda para pane elétrica e bateria descarregada.', 'Descreva o problema e veja se há atendimento compatível na região.', false],
            'pneu' => ['Troca de pneu', 'Assistência para pneu furado ou danificado.', 'Informe o local e acompanhe as alternativas de atendimento.', false],
        ];
        if (!isset($catalogo[$slug])) { http_response_code(404); echo 'Página não encontrada.'; return; }
        [$titulo, $resumo, $proximo, $exigeDestino] = $catalogo[$slug];
        MarketingEventService::recordFunnel('service_view', ['slug' => $slug], $slug);
        $tipo = 'servico';
        require __DIR__ . '/../Views/public/seo/marketing_page.php';
    }

    public function parceiro(string $slug): void
    {
        $csrf_token = $this->generateCSRFToken();
        $catalogo = [
            'seguradoras' => ['Seguradoras e corretores', 'Ofereça uma jornada digital de assistência veicular aos seus segurados.', 'Indicação, cobertura e acompanhamento com regras comerciais claras.', 'hero-seguradoras.png', 'Seguradoras e corretores', 'Ative uma alternativa rastreável para o segurado quando ele precisar de guincho, oficina ou orientação.', 'Apólices, carteiras e canais de atendimento que querem ampliar a experiência pós-sinistro.'],
            'frotas' => ['Gestores de frotas e locadoras', 'Centralize solicitações de assistência para veículos de trabalho.', 'Receba oportunidades compatíveis e acompanhe cada atendimento.', 'hero-frotas.png', 'Gestores de frotas e locadoras', 'Organize chamados por veículo, região e necessidade, com visibilidade para a continuidade da operação.', 'Frotas leves, locadoras e operações que precisam reduzir tempo parado sem perder controle.'],
            'postos-estacionamentos' => ['Postos e estacionamentos', 'Ajude seu cliente no momento em que o veículo para.', 'Indique uma assistência confiável sem precisar operar guincho ou oficina.', 'hero-postos-estacionamentos.png', 'Postos e estacionamentos', 'Ofereça um próximo passo simples para motoristas que enfrentam pane, bateria, pneu ou necessidade de reboque.', 'Postos, estacionamentos e estabelecimentos que recebem motoristas no momento da necessidade.'],
            'concessionarias-rastreadores' => ['Concessionárias e rastreadores', 'Estenda o relacionamento com o motorista depois da venda ou da instalação.', 'Transforme a assistência em uma experiência acompanhada e mensurável.', 'hero-concessionarias-rastreadores.png', 'Concessionárias e rastreadores', 'Entregue um canal de assistência coerente com sua marca e acompanhe a jornada sem criar uma operação paralela.', 'Concessionárias, lojas de veículos, rastreadores e empresas de tecnologia automotiva.'],
            'indicacao' => ['Programa de indicação', 'Indique o GuinchaFácil quando um cliente precisar de assistência.', 'A parceria pode gerar créditos ou comissão conforme o acordo aprovado.', 'hero-indicacao.png', 'Programa de indicação', 'Compartilhe um link ou código de indicação e acompanhe as conversões elegíveis conforme as regras comerciais.', 'Empresas com audiência complementar que querem ajudar o cliente sem vender o mesmo serviço.'],
        ];
        if (!isset($catalogo[$slug])) { http_response_code(404); echo 'Página não encontrada.'; return; }
        [$titulo, $resumo, $proximo, $hero_image, $segmento, $oportunidade, $perfil_ideal] = $catalogo[$slug];
        $tipo = 'parceiro';
        MarketingEventService::recordFunnel('partner_page_view', ['slug' => $slug, 'segmento' => $segmento], $slug);
        require_once __DIR__ . '/../Services/PartnerReferralCommissionService.php';
        $comissao_parceiro_percentual = PartnerReferralCommissionService::currentPercent('distribuicao');
        require __DIR__ . '/../Views/public/seo/marketing_page.php';
    }
}
