import {
  $createParagraphNode,
  $createTextNode,
  $getRoot,
  createEditor,
} from 'lexical';
import {
  HeadingNode,
  QuoteNode,
  registerRichText,
} from '@lexical/rich-text';

export function createErasedEditor(rootElement) {
  if (!(rootElement instanceof HTMLElement)) {
    throw new TypeError('ERASED editor requires a valid HTML element.');
  }

  const editor = createEditor({
    namespace: 'ERASED-CMS',
    nodes: [HeadingNode, QuoteNode],
    onError(error) {
      console.error('ERASED Lexical editor error:', error);
    },
    theme: {
      paragraph: 'erased-editor__paragraph',
      heading: {
        h1: 'erased-editor__heading erased-editor__heading--h1',
        h2: 'erased-editor__heading erased-editor__heading--h2',
      },
      quote: 'erased-editor__quote',
      text: {
        bold: 'erased-editor__text--bold',
        italic: 'erased-editor__text--italic',
        underline: 'erased-editor__text--underline',
      },
    },
  });

  editor.setRootElement(rootElement);
  const unregisterRichText = registerRichText(editor);

  editor.update(() => {
    const root = $getRoot();

    if (root.isEmpty()) {
      const paragraph = $createParagraphNode();
      paragraph.append($createTextNode('Start writing…'));
      root.append(paragraph);
    }
  });

  return {
    editor,
    destroy() {
      unregisterRichText();
      editor.setRootElement(null);
    },
  };
}
