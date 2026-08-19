const DANGEROUS_TAGS = new Set([
  'SCRIPT',
  'STYLE',
  'OBJECT',
  'EMBED',
  'LINK',
  'META',
  'BASE',
  'FORM',
  'INPUT',
  'BUTTON',
  'TEXTAREA',
  'SELECT',
  'OPTION',
  'TEMPLATE',
  'SVG',
  'MATH',
]);

const GLOBAL_ATTRIBUTES = new Set([
  'class',
  'title',
  'lang',
  'dir',
  'style',
  'data-align',
  'aria-label',
]);

const TAG_ATTRIBUTES = {
  A: new Set(['href', 'target', 'rel']),
  DIV: new Set(['width', 'height']),
  FIGURE: new Set(['width', 'height']),
  IMG: new Set(['src', 'alt', 'width', 'height', 'loading']),
  VIDEO: new Set([
    'src',
    'controls',
    'preload',
    'poster',
    'width',
    'height',
    'muted',
    'loop',
    'playsinline',
  ]),
  SOURCE: new Set(['src', 'type']),
  IFRAME: new Set([
    'src',
    'title',
    'width',
    'height',
    'allow',
    'allowfullscreen',
    'loading',
    'referrerpolicy',
    'sandbox',
  ]),
  OL: new Set(['start', 'reversed', 'type']),
  LI: new Set(['value']),
  TH: new Set(['colspan', 'rowspan', 'scope']),
  TD: new Set(['colspan', 'rowspan']),
};

const STYLE_PROPERTIES = new Set([
  'color',
  'background-color',
  'text-align',
  'width',
  'height',
  'max-width',
  'min-width',
  'margin',
  'margin-left',
  'margin-right',
  'float',
  'display',
  'aspect-ratio',
]);

const EMBED_HOSTS = new Set([
  'youtube.com',
  'www.youtube.com',
  'youtube-nocookie.com',
  'www.youtube-nocookie.com',
  'player.vimeo.com',
]);

function sanitizeStyle(value) {
  return String(value ?? '')
    .split(';')
    .map((declaration) => declaration.trim())
    .filter(Boolean)
    .map((declaration) => declaration.split(':'))
    .filter((parts) => parts.length >= 2)
    .map(([property, ...rest]) => [
      property.trim().toLowerCase(),
      rest.join(':').trim(),
    ])
    .filter(([property, propertyValue]) => (
      STYLE_PROPERTIES.has(property)
      && !/url\s*\(|expression\s*\(|javascript:|vbscript:|behavior\s*:|-moz-binding|@import/i.test(propertyValue)
      && /^[#(),.%\sa-z0-9+\-/*]+$/i.test(propertyValue)
    ))
    .map(([property, propertyValue]) => `${property}: ${propertyValue}`)
    .join('; ');
}

export function sanitizeUrl(value, tagName = '', attributeName = '') {
  const url = String(value ?? '').trim();
  if (!url || /[\u0000-\u001f\u007f]/.test(url)) return '';
  if (url.startsWith('/') && !url.startsWith('//')) return url;
  if (url.startsWith('#')) return url;
  if (/^https?:\/\//i.test(url)) return url;
  if (attributeName === 'href' && /^(?:mailto|tel):/i.test(url)) return url;
  if (
    tagName === 'IMG'
    && attributeName === 'src'
    && /^data:image\/(?:png|jpeg|gif|webp);base64,/i.test(url)
  ) {
    return url;
  }
  return '';
}

export function sanitizeAttributes(tagName, attributes = {}) {
  const tag = String(tagName || '').toUpperCase();
  const tagAllowed = TAG_ATTRIBUTES[tag] ?? new Set();
  const output = {};

  for (const [rawName, rawValue] of Object.entries(attributes)) {
    const name = String(rawName).toLowerCase();
    const value = String(rawValue ?? '').trim();

    if (
      name.startsWith('on')
      || (!GLOBAL_ATTRIBUTES.has(name) && !tagAllowed.has(name))
    ) {
      continue;
    }

    if (['href', 'src', 'poster'].includes(name)) {
      const safeUrl = sanitizeUrl(value, tag, name);
      if (safeUrl) output[name] = safeUrl;
      continue;
    }

    if (name === 'style') {
      const style = sanitizeStyle(value);
      if (style) output.style = style;
      continue;
    }

    if (name === 'class') {
      const classes = value
        .split(/\s+/)
        .filter((className) => /^[A-Za-z0-9_-]+$/.test(className))
        .filter((className) => ![
          'erased-block',
          'is-selected',
          'is-dragging',
          'erased-image-node--selected',
          'erased-video-node--selected',
        ].includes(className));
      if (classes.length) output.class = classes.join(' ');
      continue;
    }

    output[name] = value;
  }

  return output;
}

export function sanitizeHtmlFragment(html) {
  const parsed = new window.DOMParser().parseFromString(
    `<!doctype html><html><body>${String(html ?? '')}</body></html>`,
    'text/html',
  );
  const root = parsed.body;

  root.querySelectorAll('*').forEach((element) => {
    if (DANGEROUS_TAGS.has(element.tagName)) {
      element.remove();
      return;
    }

    const attributes = Object.fromEntries(
      Array.from(element.attributes).map(({ name, value }) => [name, value]),
    );
    const clean = sanitizeAttributes(element.tagName, attributes);

    Array.from(element.attributes).forEach(({ name }) => {
      element.removeAttribute(name);
    });
    Object.entries(clean).forEach(([name, value]) => {
      element.setAttribute(name, value);
    });

    if (element.tagName === 'IFRAME') {
      const source = element.getAttribute('src') || '';
      let host = '';
      try {
        host = new URL(
          source,
          window.location?.origin ?? 'http://localhost',
        ).hostname.toLowerCase();
      } catch {
        host = '';
      }
      if (!EMBED_HOSTS.has(host)) {
        element.remove();
        return;
      }
      element.setAttribute(
        'sandbox',
        'allow-scripts allow-same-origin allow-presentation',
      );
      element.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
      element.setAttribute('loading', 'lazy');
    }

    if (
      element.tagName === 'A'
      && element.getAttribute('target')?.toLowerCase() === '_blank'
    ) {
      element.setAttribute('rel', 'noopener noreferrer');
    }
  });

  return root.innerHTML;
}
