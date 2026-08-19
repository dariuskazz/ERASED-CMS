import './styles/editor.css';
import { createErasedEditor } from './core/Editor.js';

export { createErasedEditor };

function mountEditors() {
  document.querySelectorAll('[data-erased-lexical]').forEach((element) => {
    if (element.dataset.erasedLexicalMounted === 'true') {
      return;
    }

    element.dataset.erasedLexicalMounted = 'true';
    createErasedEditor(element);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountEditors, { once: true });
} else {
  mountEditors();
}
