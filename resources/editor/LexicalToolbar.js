import { $getSelection, $isRangeSelection } from 'lexical';
import { $isHeadingNode } from '@lexical/rich-text';
import { $isListNode } from '@lexical/list';
import { $isLinkNode } from '@lexical/link';
import './toolbar.css';

function adapter() {
  return window.erasedEditor && typeof window.erasedEditor.command === 'function'
    ? window.erasedEditor
    : null;
}

function execute(command, value = null) {
  const editor = adapter();
  if (!editor) return;

  const element = editor.options?.element || document.querySelector('#visual-editor');
  element?.focus();
  editor.command(command, value);
  element?.focus();
}

export function initializeToolbar() {
  const toolbar = document.querySelector('#lexical-toolbar');
  if (!toolbar || toolbar.dataset.initialized === 'true') return;
  toolbar.dataset.initialized = 'true';

  toolbar.addEventListener('mousedown', (event) => {
    if (event.target.closest('button')) {
      event.preventDefault();
    }
  });

  toolbar.addEventListener('click', (event) => {
    const btn = event.target.closest('button');
    if (!btn) return;

    const command = btn.dataset.lexicalCommand;
    if (command) {
      execute(command);
      return;
    }

    if (btn.dataset.lexicalLink !== undefined) {
      const url = window.prompt('Enter link URL:', 'https://');
      if (url?.trim()) {
        execute('createLink', url.trim());
      }
    }
  });

  toolbar.addEventListener('change', (event) => {
    const select = event.target.closest('select');
    if (select) {
      if (select.dataset.lexicalFormatBlock !== undefined) {
        if (select.value) {
          execute('formatBlock', select.value);
        }
      } else if (select.dataset.lexicalAlign !== undefined) {
        if (select.value) {
          execute(select.value);
          select.value = '';
        }
      }
      return;
    }

    const input = event.target.closest('input[data-lexical-color]');
    if (input) {
      const command = input.dataset.lexicalColor;
      execute(command, input.value);
    }
  });
}

export function updateToolbarState(editorState) {
  const toolbar = document.querySelector('#lexical-toolbar');
  if (!toolbar) return;

  editorState.read(() => {
    const selection = $getSelection();
    if (!$isRangeSelection(selection)) return;

    const isBold = selection.hasFormat('bold');
    const isItalic = selection.hasFormat('italic');
    const isUnderline = selection.hasFormat('underline');
    const isStrikethrough = selection.hasFormat('strikethrough');
    const isSubscript = selection.hasFormat('subscript');
    const isSuperscript = selection.hasFormat('superscript');
    const isCode = selection.hasFormat('code');

    updateButtonState(toolbar, 'bold', isBold);
    updateButtonState(toolbar, 'italic', isItalic);
    updateButtonState(toolbar, 'underline', isUnderline);
    updateButtonState(toolbar, 'strikeThrough', isStrikethrough);
    updateButtonState(toolbar, 'subscript', isSubscript);
    updateButtonState(toolbar, 'superscript', isSuperscript);
    updateButtonState(toolbar, 'code', isCode);

    const anchorNode = selection.anchor.getNode();
    const element = anchorNode.getKey() === 'root'
      ? anchorNode
      : anchorNode.getTopLevelElementOrThrow();

    const blockSelect = toolbar.querySelector('select[data-lexical-format-block]');
    if (blockSelect) {
      if ($isHeadingNode(element)) {
        blockSelect.value = element.getTag();
      } else if (element.getType() === 'quote') {
        blockSelect.value = 'blockquote';
      } else {
        blockSelect.value = 'p';
      }
    }

    const isTable = !!anchorNode.findMatchingParent((n) => n.getType() === 'table');
    const tableGroup = toolbar.querySelector('.lexical-playground-toolbar__group--table');
    if (tableGroup) {
      tableGroup.style.display = isTable ? 'inline-flex' : 'none';
    }
  });
}

function updateButtonState(toolbar, command, active) {
  const btn = toolbar.querySelector(`button[data-lexical-command="${command}"]`);
  if (btn) {
    btn.classList.toggle('is-active', active);
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeToolbar);
} else {
  initializeToolbar();
}
