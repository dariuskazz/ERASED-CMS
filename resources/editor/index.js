import { LexicalAdapter } from './LexicalAdapter.js';
import './LexicalToolbar.js';
import './editor.css';
import './toolbar.css';

function syncBodyField(htmlOverride = null) {
  const field = document.querySelector('#body-editor');
  const element = document.querySelector('#visual-editor');

  if (!field || !element) {
    return '';
  }

  const html = typeof htmlOverride === 'string'
    ? htmlOverride
    : (window.erasedEditor?.getHTML?.() ?? element.innerHTML);
  field.value = html;
  return html;
}

function boot() {
  const element = document.querySelector('#visual-editor');
  const field = document.querySelector('#body-editor');
  const form = document.querySelector('#content-form');

  if (!element || window.erasedEditor) {
    return;
  }

  const initialHTML = field?.value || element.innerHTML || '';

  window.erasedEditor = new LexicalAdapter({
    element,
    content: initialHTML,
    onUpdate(html) {
      syncBodyField(html);
    },
  }).mount();

  form?.addEventListener('submit', () => {
    syncBodyField();
  }, true);

  form?.addEventListener('formdata', (event) => {
    event.formData.set('body', syncBodyField());
  });

  window.syncLexicalBody = syncBodyField;
  syncBodyField();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
