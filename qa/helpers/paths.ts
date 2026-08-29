function configuredBasePath(): string {
  const fromEnv = process.env.PLAYWRIGHT_APP_BASE_PATH || '';
  if (fromEnv) {
    return normalizeBasePath(fromEnv);
  }

  const fromBaseUrl = process.env.PLAYWRIGHT_BASE_URL || '';
  if (!fromBaseUrl) {
    return '';
  }

  try {
    const url = new URL(fromBaseUrl);
    return normalizeBasePath(url.pathname || '');
  } catch {
    return '';
  }
}

function normalizeBasePath(input: string): string {
  const trimmed = input.trim();
  if (!trimmed || trimmed === '/') {
    return '';
  }

  const normalized = '/' + trimmed.replace(/^\/+|\/+$/g, '');
  return normalized === '/' ? '' : normalized;
}

export function appPath(path: string): string {
  const route = path.startsWith('/') ? path : `/${path}`;
  const basePath = configuredBasePath();
  return `${basePath}${route}`;
}
