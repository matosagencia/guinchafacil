/*
 * GuinchaFácil — preenchimento assistido do Google Ads.
 * Cole no Console da página de criação do anúncio.
 * Não publica a campanha nem envia dados: apenas preenche campos encontrados.
 */
(() => {
  'use strict';

  const assets = {
    business: 'GuinchaFácil',
    finalUrl: 'https://guinchafacil.com.br/',
    headlines: [
      'Guincho em Niterói',
      'Peça Socorro Veicular',
      'Cotação Antes de Pagar',
      'Guincho Perto de Você',
      'Carro Parou? A gente ajuda',
      'Assistência na Estrada',
      'Bateria, Pneu ou Reboque',
      'Socorro Veicular Online',
      'Ajuda para seu veículo',
      'Preço claro antes de seguir',
      'Reboque após colisão',
      'Pneu furado? Peça ajuda',
      'Bateria descarregada?',
      'Pane seca? Veja opções',
      'Profissionais parceiros',
    ],
    longHeadlines: [
      'Seu carro parou? Informe o local e veja uma cotação de assistência veicular',
      'GuinchaFácil conecta você a parceiros de reboque e socorro em Niterói',
      'Bateria, pneu, pane ou colisão: escolha a assistência para o seu caso',
    ],
    descriptions: [
      'Informe o local e o problema. Veja a cotação antes de criar sua conta.',
      'Bateria, pneu, pane ou colisão. Encontre assistência parceira na região.',
      'Processo simples, localização conferida e preço explicado antes da confirmação.',
      'A plataforma conecta você a profissionais parceiros para ajudar na estrada.',
    ],
    sitelinks: [
      ['Pedir cotação', 'Veja uma cotação online', 'Sem cobrança nesta etapa', 'https://guinchafacil.com.br/pre-cotacao'],
      ['Assistência veicular', 'Bateria, pneu e panes', 'Escolha o tipo de ajuda', 'https://guinchafacil.com.br/pre-cotacao'],
      ['Como funciona', 'Entenda os 3 passos', 'Local, problema e cotação', 'https://guinchafacil.com.br/#como-funciona'],
      ['Seja parceiro', 'Atenda clientes da região', 'Cadastre sua operação', 'https://guinchafacil.com.br/registro/guincho'],
    ],
  };

  const norm = s => (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const visible = el => !!(el && el.offsetParent !== null);
  const all = sel => [...document.querySelectorAll(sel)].filter(visible);
  const labelText = el => {
    const aria = el.getAttribute('aria-label') || '';
    const ph = el.getAttribute('placeholder') || '';
    const labelled = el.getAttribute('aria-labelledby');
    const byId = labelled ? document.getElementById(labelled)?.innerText || '' : '';
    return norm([aria, ph, byId, el.parentElement?.innerText || ''].join(' '));
  };
  const setValue = (el, value) => {
    const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(el), 'value')?.set;
    if (setter) setter.call(el, value); else el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    el.style.outline = '3px solid #2fb34a';
  };

  function fillGroup(values, selectors, keywords) {
    let fields = selectors.flatMap(s => all(s));
    fields = [...new Set(fields)];
    if (!fields.length) fields = all('input, textarea').filter(el => {
      const t = labelText(el);
      return keywords.some(k => t.includes(norm(k)));
    });
    fields.slice(0, values.length).forEach((el, i) => setValue(el, values[i]));
    return fields.length;
  }

  const filled = {
    headlines: fillGroup(assets.headlines, ['input[ maxlength="30" ]', 'input[maxlength="30"]'], ['título', 'headline']),
    longHeadlines: fillGroup(assets.longHeadlines, ['input[ maxlength="90" ]', 'input[maxlength="90"]'], ['título longo', 'long headline']),
    descriptions: fillGroup(assets.descriptions, ['textarea[ maxlength="90" ]', 'textarea[maxlength="90"]', 'input[ maxlength="90" ]'], ['descrição', 'description']),
  };

  // URL final: só preenche se houver um campo claramente identificado.
  const url = all('input[type="url"], input').find(el => {
    const t = labelText(el); return t.includes('url final') || t.includes('pagina de destino') || t.includes('final url');
  });
  if (url) setValue(url, assets.finalUrl);

  // Nome da empresa, quando o campo estiver disponível.
  const company = all('input').find(el => labelText(el).includes('nome da empresa'));
  if (company) setValue(company, assets.business);

  // Sitelinks: preenche grupos visíveis pela ordem em que aparecem.
  const linkFields = all('input, textarea').filter(el => {
    const t = labelText(el);
    return t.includes('sitelink') || t.includes('texto do link') || t.includes('descricao do sitelink') || t.includes('url do sitelink');
  });
  assets.sitelinks.flat().forEach((value, i) => { if (linkFields[i]) setValue(linkFields[i], value); });

  console.table({
    'Títulos encontrados': filled.headlines,
    'Títulos longos encontrados': filled.longHeadlines,
    'Descrições encontradas': filled.descriptions,
    'Sitelinks preenchidos': Math.min(linkFields.length, assets.sitelinks.flat().length),
    'URL encontrada': !!url,
    'Empresa encontrada': !!company,
  });
  console.info('Imagens e logo: selecione manualmente os arquivos preparados. Não publique sem revisar prévias, localização e política da campanha.');
})();
