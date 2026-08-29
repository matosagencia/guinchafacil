import type {
  FullConfig,
  FullResult,
  Reporter,
  Suite,
  TestCase,
  TestResult
} from '@playwright/test/reporter';
import fs from 'fs';
import path from 'path';

type StepRecord = {
  code: string;
  title: string;
  status: string;
  message: string;
  duration_ms: number;
  file?: string;
  phase?: string;
  system?: string;
  class?: string;
  function?: string;
  error?: string;
  artifacts?: StepArtifactRecord[];
};

type StepArtifactRecord = {
  name: string;
  content_type?: string;
  path?: string;
  kind: string;
};

class GuinchaFacilReporter implements Reporter {
  private readonly runId = process.env.QA_RUN_ID || 'local';
  private readonly resultJson = process.env.QA_RESULT_JSON || path.join(process.cwd(), 'result.json');
  private readonly artifactsDir = process.env.QA_ARTIFACTS_DIR || path.join(process.cwd(), 'test-results');
  private readonly steps: StepRecord[] = [];
  private startedAt = Date.now();

  onBegin(_config: FullConfig, _suite: Suite): void {
    this.startedAt = Date.now();
  }

  onTestEnd(test: TestCase, result: TestResult): void {
    const titlePath = test.titlePath().join(' > ');
    const code = test.title.split('|')[0]?.trim() || 'E2E-TEST';
    const artifacts = result.attachments
      .filter((attachment) => attachment.path || attachment.body)
      .map((attachment) => ({
        name: attachment.name,
        content_type: attachment.contentType,
        path: attachment.path ? this.toArtifactPath(attachment.path) : undefined,
        kind: this.guessArtifactKind(attachment)
      }));

    // Antes disso, todo teste aparecia com system=E2E/class=PlaywrightSuite/
    // function=test — genérico demais pra saber ONDE dentro do teste algo
    // quebrou. RegistroPassos (step-logger.ts) já anexa um
    // `falha-<passo>.json` estruturado (system/class/function/phase/pedidoId)
    // no exato passo que falhou; se existir, usamos esses dados reais em vez
    // do placeholder genérico.
    const metadataDoPassoQueFalhou = this.extrairMetadataDoPassoFalho(result);

    this.steps.push({
      code,
      title: titlePath,
      status: result.status,
      message: result.error?.message || result.status,
      duration_ms: result.duration,
      file: test.location.file,
      phase: metadataDoPassoQueFalhou?.phase || 'test',
      system: metadataDoPassoQueFalhou?.system || 'E2E',
      class: metadataDoPassoQueFalhou?.class || 'PlaywrightSuite',
      function: metadataDoPassoQueFalhou?.function || 'test',
      error: result.error?.stack,
      artifacts
    });
  }

  private extrairMetadataDoPassoFalho(result: TestResult): { system?: string; class?: string; function?: string; phase?: string; pedidoId?: string } | null {
    const attachmentFalha = result.attachments.find((a) => /^falha-.*\.json$/.test(a.name));
    if (!attachmentFalha) return null;

    try {
      const raw = attachmentFalha.body
        ? attachmentFalha.body.toString('utf8')
        : attachmentFalha.path
          ? fs.readFileSync(attachmentFalha.path, 'utf8')
          : null;
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      return {
        system: parsed.system,
        class: parsed.class,
        function: parsed.function,
        phase: parsed.phase,
        pedidoId: parsed.pedidoId !== undefined ? String(parsed.pedidoId) : undefined
      };
    } catch {
      return null;
    }
  }

  onEnd(result: FullResult): void {
    const failed = this.steps.filter((step) => step.status !== 'passed').length;
    const payload = {
      run_id: this.runId,
      status: result.status === 'passed' ? 'completed' : result.status === 'timedout' ? 'timeout' : 'failed',
      exit_code: failed > 0 ? 1 : 0,
      duration_ms: Date.now() - this.startedAt,
      total_steps: this.steps.length,
      failed_steps: failed,
      steps: this.steps
    };

    fs.mkdirSync(path.dirname(this.resultJson), { recursive: true });
    fs.writeFileSync(this.resultJson, JSON.stringify(payload, null, 2), 'utf8');
  }

  private toArtifactPath(filePath: string): string {
    const absolute = path.resolve(filePath);
    const base = path.resolve(this.artifactsDir);

    if (absolute.startsWith(base)) {
      return absolute;
    }

    return absolute;
  }

  private guessArtifactKind(attachment: TestResult['attachments'][number]): string {
    const name = (attachment.name || '').toLowerCase();
    const contentType = (attachment.contentType || '').toLowerCase();
    const filePath = (attachment.path || '').toLowerCase();

    if (name.includes('trace') || filePath.endsWith('.zip')) {
      return 'trace';
    }
    if (name.includes('video') || contentType.startsWith('video/') || filePath.endsWith('.webm')) {
      return 'video';
    }
    if (name.includes('screenshot') || contentType.startsWith('image/') || filePath.endsWith('.png') || filePath.endsWith('.jpg') || filePath.endsWith('.jpeg')) {
      return 'screenshot';
    }

    return 'attachment';
  }
}

export default GuinchaFacilReporter;
