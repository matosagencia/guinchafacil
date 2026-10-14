```markdown
# GuinchaFácil — Documento de Precificação

**Versão 1.0**  
**Data:** 12 de outubro de 2026  
**Baseado em:** teste real de mercado + pesquisa de referências 2025-2026

---

## 1. Contexto e Objetivo

Este documento consolida a análise de precificação do GuinchaFácil a partir de um teste real de cotação, dados de mercado brasileiros e internacionais, e recomendações práticas para o motor de decisão (`DecisaoAtendimentoService` / `PricingService`).

O objetivo é definir uma estrutura de tarifas que:

- Seja aceitável tanto para o cliente quanto para o parceiro (guincheiro ou socorrista);
- Cubra deslocamento, terreno, deadhead e risco operacional;
- Permita comissão sustentável da plataforma sem comprometer o aceite;
- Diferencie claramente **assistência no local** de **reboque**.

---

## 2. Dado Concreto da Conversa (Teste Real – 12/10/2026)

### Trecho da conversa analisada

- Bruno solicita cotação.
- Guincho Almeida responde **R$ 1.000**.
- Cliente declina: “conseguiu por R$ 650”.
- Distância informada inicialmente: ~63 km (marco da rodovia “Km 63”).
- **Endereços reais:**
  - **Origem:** Posto Guadalupe – Rod. Juvenal Ponciano de Camargo, Km 63 – Centro, Nazaré Paulista/SP.
  - **Destino:** Av. Santa Bárbara do Rio Pardo, 1226 – Jd. Nova Europa, Campinas/SP.
- Distância real medida (Google): **≈ 93 km** (1h22).
- Pedágio aparente no print: ~R$ 27,20.
- Almeida confirma que o valor de R$ 650 é “pouco mais da metade” e observa que “cada profissional tem sua realidade”.
- Bruno estima deslocamento extra do guincho de 10–20 km → total rodado próximo de 100 km.

### Leitura do teste

| Valor ofertado | Km real | R$/km aproximado |
|----------------|---------|------------------|
| R$ 1.000       | 93 km   | ≈ R$ 10,75/km   |
| R$ 650         | 93 km   | ≈ R$ 7,00/km    |

- Com deadhead de 10–20 km, o valor efetivo cai ainda mais.
- A fórmula antiga do projeto (R$ 150 fixos + R$ 3,50/km) geraria apenas **R$ 475,50** para 93 km — claramente abaixo do mercado e do break-even dos parceiros.

**Este caso é o ponto de ancoragem principal para calibração.**

---

## 3. Estrutura de Mercado Observada (2025-2026)

### 3.1 Reboque / Guincho (carro de passeio)

| Componente                    | Faixa observada (SP / interior)      | Fonte principal                  |
|-------------------------------|--------------------------------------|----------------------------------|
| Taxa de saída / engate        | R$ 120 – R$ 250                     | Ache Guincho, prestadores locais |
| Valor por km                  | R$ 5,00 – R$ 8,00                   | Ache Guincho 2026                |
| Longa distância (>80-100 km)  | R$ 2,50 – R$ 4,50/km (com taxa maior)| Prática internacional + operadores BR |
| Noturno / feriado             | +20% a +50%                         | Comum no setor                   |
| Acesso difícil / terreno      | Taxa extra ou % adicional           | Operadores e literatura          |

Referências internacionais (EUA 2026) confirmam estrutura similar: taxa de engate + km após raio livre, com redução de km em longas distâncias e absorção de deadhead na taxa fixa.

### 3.2 Assistência no Local (Socorro)

| Serviço                                      | Faixa típica avulsa          | Observação                                      |
|----------------------------------------------|------------------------------|-------------------------------------------------|
| Auxílio de partida / carga de bateria        | R$ 80 – R$ 200              | Mais frequente                                  |
| Troca de pneu                                | R$ 80 – R$ 180              | —                                               |
| Pane seca                                    | R$ 80 – R$ 200 + combustível| —                                               |
| Tentativa de pane mecânica simples           | R$ 150 – R$ 350             | Trambulador, cabo de embreagem, superaquecimento leve |
| Acionamento geral curto                      | R$ 150 – R$ 300             | Até ~15-20 km                                   |

---

## 4. Fator Terreno / Elevação / Acesso ao Hook-up

A hipótese de que ladeiras (inclinação) e elevação impactam o custo é **confirmada** por física e prática operacional.

### Grade (inclinação %) – padrão da indústria

- **0–3%**: impacto mínimo
- **4–5%**: moderado
- **≥ 6%**: íngreme (aumento significativo de consumo, desgaste de freio/transmissão e risco)
- **≥ 8%**: muito íngreme (técnicas especiais, maior tempo e risco)

Estudos de consumo em caminhões mostram que a grade pode representar de 1% a 40% do consumo extra em trechos específicos. Altitude: perda de potência de ~3–4% a cada ~300 m. Acesso ao ponto de engate (ladeira, rua estreita, terreno irregular) aumenta tempo e risco de escorregamento na plataforma.

### Recomendação de implementação

- Usar **Google Elevation API** ou **Open-Elevation / SRTM** ao longo da polyline da rota + ponto de origem.
- **Métricas:** grau máximo sustentado, comprimento de trechos ≥ 6%, ganho de elevação total, inclinação no raio de 100–200 m do hook-up.
- **Gatilhos iniciais sugeridos:**
  - Grade ≥ 6% por ≥ 1 km → +8–12% ou taxa fixa R$ 40–80
  - Grade ≥ 8% → +15–25% ou taxa R$ 80–150
  - Ganho de elevação > 250–300 m → +5–10%
  - Hook-up em ladeira acentuada → taxa de manobra R$ 50–120

O fator deve ser **configurável no admin** e calibrado com dados reais.

---

## 5. Modelo de Tarifação Recomendado

### 5.1 Reboque (trajeto completo)

```text
Preço Cliente = Taxa de engate + (km_trajeto × tarifa_km escalonada) × fator_terreno + pedágio + extras
```

| Faixa de km (trajeto) | Taxa de engate | Tarifa/km | Exemplo 93 km* |
|-----------------------|----------------|-----------|----------------|
| 0–30 km              | R$ 220        | R$ 8,50  | —              |
| 31–70 km             | R$ 180        | R$ 6,80  | —              |
| **71–120 km**        | **R$ 160**    | **R$ 5,80** | **R$ 699**  |
| >120 km              | R$ 140        | R$ 4,90  | —              |

\* Sem pedágio e sem fator terreno.  
- Pedágio: 100% repassado.  
- Piso mínimo para longas: R$ 650.  
- Deadhead: absorvido na taxa de engate + estimativa de deslocamento do parceiro no alerta.

**Aplicação ao caso real (93 km):** preço alvo cliente ≈ **R$ 700–780** (dentro da faixa de percepção do R$ 650 e abaixo do R$ 1.000).

### 5.2 Assistência no Local (Taxa de Visita + Tentativa)

```text
Taxa de Visita = Taxa base de deslocamento + (km_deslocamento × tarifa_km) + fator_terreno + extras
```

- Taxa base: **R$ 120–160** (até 10–15 km)
- Km excedente: **R$ 4,50–6,00**
- Fator terreno: mesma lógica do reboque
- Resultado típico urbano/interior próximo: **R$ 160–220**

**Regra de negócio:**

- O valor da Taxa de Visita cobre apenas o deslocamento + tentativa.
- Mão de obra + peças do reparo ficam a cargo do socorrista e do cliente no local.
- Se não resolver → fallback automático para a cotação de reboque (com possível desconto de 10–15% por já ter pago a visita).

### 5.3 Comissão da Plataforma (Take Rate)

- **Não usar 20%** no valor total do guincho neste estágio (fica fora da realidade de aceite).
- **Modelo recomendado inicial (híbrido):**
  - R$ 30–50 fixos + 8–12%, **ou**
  - 10–15% pure sobre o valor cobrado do cliente.
- Em corridas longas, preferir % menor ou teto para proteger o parceiro.
- Meta de liquidez primeiro: começar baixo e subir conforme densidade da rede.

### 5.4 Percepção de Preço

Aplicando a ideia de faixa de aceitação (±10–20%, frequentemente citada em torno de 15%):

- Tomando R$ 650 como referência do cliente → faixa aproximada **R$ 553 – R$ 748**.
- R$ 1.000 fica 54% acima (fora).
- R$ 475 (fórmula antiga) fica 27% abaixo (bom para o cliente, ruim para o parceiro).

A referência real do cliente é o orçamento que ele já possui. O preço da plataforma deve ficar na parte alta da faixa do cliente e na parte aceitável da faixa do parceiro.

---

## 6. Recomendações de Implementação no Sistema

1. **Motor único** (`DecisaoAtendimentoService`): decidir entre “assistência no local”, “reboque direto” ou “híbrido”.
2. Gravar no pedido (`Contrato Pedido v1`): `custo_assistencia`, `custo_reboque`, `recomendacao`, dados de relevo (grau máximo, elevação, etc.).
3. Alerta ao parceiro: mostrar valor líquido estimado + km total estimado (deslocamento + trajeto + retorno parcial) + motivo de qualquer ajuste de terreno.
4. **Coleta de dados obrigatória**: toda cotação real deve registrar origem, destino, km real (Google/OSRM), valor ofertado/aceito, fonte, data, dados de elevação e se resolveu no local ou virou reboque.
5. Parâmetros (taxas, % terreno, take rate, pisos) devem ser **configuráveis no admin**.
6. Começar com a tabela acima, testar em 10–20 corridas reais e recalibrar.

---

## 7. Próximos Passos Sugeridos

1. Validar esta tabela com 3–5 parceiros reais (incluindo o perfil do Almeida).
2. Implementar o cálculo de elevação/grade no fluxo de pré-cotação.
3. Criar a tabela de cotações reais no banco.
4. Definir o fluxo de fallback (visita → reboque) com desconto automático.
5. Medir taxa de aceite do parceiro e conversão do cliente por faixa de preço.

---

## 8. Fontes Principais Utilizadas

- Conversas reais do teste de cotação (12/10/2026) — Nazaré Paulista → Campinas
- Ache Guincho – Tabela de preços por km (2026)
- Tow Truck Pro Academy – Tow Truck Rates Per Mile (2026 Operator Pricing Guide)
- Estudos de consumo de combustível por grade (MDPI Sustainability; SAE Technical Papers)
- Práticas de marketplaces de dois lados (Uber, Lyft, DoorDash, iFood – comissões e take rates)
- Comparativos de assistência 24h e prestadores avulsos em São Paulo 2025-2026
- Literatura de towing em terrenos montanhosos e deadhead miles (NEMT e trucking sources)

---

**Este documento serve como base oficial para calibração do `PricingService` e do `DecisaoAtendimentoService`.**  

Recomenda-se versioná-lo no repositório em `docs/PRECIFICACAO.md` e manter atualizado com dados reais de cotações.
```

Pode copiar e colar diretamente em `docs/PRECIFICACAO.md`.  
Quer que eu adicione alguma seção extra (ex.: exemplos de cálculo detalhados, pseudocódigo do fator terreno, ou tabela de comissão por faixa)?