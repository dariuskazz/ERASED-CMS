import {
  $createParagraphNode,
  $getNodeByKey,
  $getRoot,
  $getSelection,
  $isRangeSelection,
  $isTextNode,
  createEditor,
  FORMAT_ELEMENT_COMMAND,
  FORMAT_TEXT_COMMAND,
  REDO_COMMAND,
  UNDO_COMMAND,
} from 'lexical';
import {
  $createHeadingNode,
  $createQuoteNode,
  HeadingNode,
  QuoteNode,
  registerRichText,
} from '@lexical/rich-text';
import {
  $generateHtmlFromNodes,
  $generateNodesFromDOM,
} from '@lexical/html';
import {
  createEmptyHistoryState,
  registerHistory,
} from '@lexical/history';
import {
  INSERT_ORDERED_LIST_COMMAND,
  INSERT_UNORDERED_LIST_COMMAND,
  ListItemNode,
  ListNode,
  registerList,
} from '@lexical/list';
import {
  LinkNode,
  TOGGLE_LINK_COMMAND,
} from '@lexical/link';
import { $setBlocksType } from '@lexical/selection';
import {
  $insertTableColumn,
  $insertTableRow,
  TableCellNode,
  TableNode,
  TableRowNode,
} from '@lexical/table';
import { mergeRegister } from '@lexical/utils';
import { EditorInterface } from './EditorInterface.js';
import {
  ImageNode,
  $isImageNode,
} from './nodes/ImageNode.js';
import { RawHtmlNode } from './nodes/RawHtmlNode.js';
import {
  VideoNode,
  $isVideoNode,
} from './nodes/VideoNode.js';
import { updateToolbarState } from './LexicalToolbar.js';

export class LexicalAdapter extends EditorInterface {
  mount() {
    const element = this.options.element;

    if (!(element instanceof HTMLElement)) {
      throw new TypeError('LexicalAdapter requires a valid element.');
    }

    this.editor = createEditor({
      namespace: 'ERASED-CMS',
      nodes: [
        HeadingNode,
        QuoteNode,
        ListNode,
        ListItemNode,
        LinkNode,
        TableNode,
        TableRowNode,
        TableCellNode,
        ImageNode,
        VideoNode,
        RawHtmlNode,
      ],
      theme: {
        paragraph: 'erased-editor__paragraph',
        quote: 'erased-editor__quote',
        heading: {
          h1: 'erased-editor__heading erased-editor__heading--h1',
          h2: 'erased-editor__heading erased-editor__heading--h2',
          h3: 'erased-editor__heading erased-editor__heading--h3',
          h4: 'erased-editor__heading erased-editor__heading--h4',
        },
        list: {
          ul: 'erased-editor__list erased-editor__list--bullet',
          ol: 'erased-editor__list erased-editor__list--number',
          listitem: 'erased-editor__list-item',
          nested: {
            listitem: 'erased-editor__list-item--nested',
          },
        },
        link: 'erased-editor__link',
        table: 'erased-editor__table',
        tableCell: 'erased-editor__table-cell',
        tableCellHeader: 'erased-editor__table-cell--header',
        tableRow: 'erased-editor__table-row',
        text: {
          bold: 'erased-editor__text--bold',
          italic: 'erased-editor__text--italic',
          underline: 'erased-editor__text--underline',
          strikethrough: 'erased-editor__text--strike',
          subscript: 'erased-editor__text--subscript',
          superscript: 'erased-editor__text--superscript',
          code: 'erased-editor__text--code',
        },
      },
      onError(error) {
        console.error('ERASED Lexical editor error:', error);
      },
    });

    element.innerHTML = '';
    element.classList.add('erased-lexical-editor');
    element.setAttribute('contenteditable', 'true');
    element.setAttribute('role', 'textbox');
    element.setAttribute('aria-multiline', 'true');
    element.setAttribute('spellcheck', 'true');

    this.editor.setRootElement(element);

    const historyState = createEmptyHistoryState();

    this.unregister = mergeRegister(
      registerRichText(this.editor),
      registerHistory(this.editor, historyState, 300),
      registerList(this.editor),
      this.editor.registerUpdateListener(({ editorState }) => {
        updateToolbarState(editorState);
        editorState.read(() => {
          this.options.onUpdate?.(
            $generateHtmlFromNodes(this.editor),
          );
        });
      }),
    );

    this.setupDragAndDrop(element);
    this.setupMediaEvents(element);
    this.setHTML(this.options.content ?? '');

    return this;
  }

  setupDragAndDrop(element) {
    ['dragenter', 'dragover'].forEach((eventName) => {
      element.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        element.classList.add('erased-lexical-editor--drop-active');
      });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
      element.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        element.classList.remove('erased-lexical-editor--drop-active');
      });
    });

    element.addEventListener('drop', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const files = e.dataTransfer?.files;
      if (!files || !files.length) return;

      const csrfToken = document.querySelector('input[name=csrf]')?.value || '';

      Array.from(files).forEach((file) => {
        if (!file.type.startsWith('image/') && !file.type.startsWith('video/')) return;
        const fd = new FormData();
        fd.append('csrf', csrfToken);
        fd.append('file', file);

        fetch('/admin/media/upload', { method: 'POST', body: fd })
          .then((r) => r.json())
          .then((data) => {
            if (!data.ok) throw new Error(data.error || 'Upload failed');
            if (data.type === 'video') {
              this.insertHTML(`<figure class="post-video" data-align="center"><video controls preload="metadata"><source src="${data.url}"></video></figure><p><br></p>`);
            } else {
              const safeAlt = (data.alt || '').replace(/"/g, '&quot;');
              this.insertHTML(`<figure class="media-large media-center"><img src="${data.url}" alt="${safeAlt}"><figcaption contenteditable="true"></figcaption></figure><p><br></p>`);
            }
          })
          .catch((err) => alert(`Upload error: ${err.message}`));
      });
    });
  }

  setupMediaEvents(element) {
    const resize = (event, predicate) => {
      const detail = event.detail ?? {};
      const width = Number.parseInt(detail.width, 10);
      const height = Number.parseInt(detail.height, 10);
      const nodeKey = String(detail.nodeKey ?? '');

      if (!nodeKey || width < 1 || height < 1 || !this.editor) {
        return;
      }

      this.editor.update(() => {
        const node = $getNodeByKey(nodeKey);
        if (predicate(node)) {
          node.setDimensions(width, height);
        }
      });
    };

    const onImageResize = (event) => resize(event, $isImageNode);
    const onVideoResize = (event) => resize(event, $isVideoNode);

    element.addEventListener('erased-image-resize', onImageResize);
    element.addEventListener('erased-video-resize', onVideoResize);

    this.mediaEventCleanup = () => {
      element.removeEventListener('erased-image-resize', onImageResize);
      element.removeEventListener('erased-video-resize', onVideoResize);
    };
  }

  destroy() {
    this.unregister?.();
    this.unregister = null;
    this.mediaEventCleanup?.();
    this.mediaEventCleanup = null;

    if (this.editor) {
      this.editor.setRootElement(null);
      this.editor = null;
    }
  }

  getHTML() {
    if (!this.editor) {
      return '';
    }

    let html = '';

    this.editor.getEditorState().read(() => {
      html = $generateHtmlFromNodes(this.editor);
    });

    return html;
  }

  setHTML(html) {
    if (!this.editor) {
      return this;
    }

    this.editor.update(() => {
      const root = $getRoot();
      root.clear();

      if (html && String(html).trim() !== '') {
        try {
          const document = new DOMParser().parseFromString(
            String(html),
            'text/html',
          );

          const nodes = $generateNodesFromDOM(
            this.editor,
            document,
          );

          root.append(...nodes);
        } catch (e) {
          console.error('Error importing DOM into Lexical:', e);
        }
      }

      if (root.isEmpty()) {
        root.append($createParagraphNode());
      }
    });

    const visualEditor = document.querySelector('#visual-editor');
    if (visualEditor) {
      visualEditor.scrollTop = 0;
    }

    return this;
  }

  insertHTML(html) {
    if (!this.editor || !html) {
      return this;
    }

    this.editor.update(() => {
      const document = new DOMParser().parseFromString(
        String(html),
        'text/html',
      );

      const nodes = $generateNodesFromDOM(
        this.editor,
        document,
      );

      if (nodes.length === 0) {
        console.warn(
          'Lexical generated no nodes from inserted HTML:',
          html,
        );
        return;
      }

      let selection = $getSelection();
      if (!selection || !$isRangeSelection(selection)) {
        const root = $getRoot();
        selection = root.selectEnd();
      }

      selection.insertNodes(nodes);
    });

    this.editor.focus();
    return this;
  }

  command(name, value = null) {
    if (!this.editor) {
      return false;
    }

    this.editor.focus();

    const commands = {
      undo: () => this.editor.dispatchCommand(UNDO_COMMAND, undefined),
      redo: () => this.editor.dispatchCommand(REDO_COMMAND, undefined),
      bold: () => this.editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'bold'),
      italic: () => this.editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'italic'),
      underline: () => this.editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'underline'),
      strikeThrough: () => this.editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'strikethrough'),
      subscript: () => this.editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'subscript'),
      superscript: () => this.editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'superscript'),
      code: () => this.editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'code'),
      alignLeft: () => this.formatAlignment('left'),
      alignCenter: () => this.formatAlignment('center'),
      alignRight: () => this.formatAlignment('right'),
      alignJustify: () => this.formatAlignment('justify'),
      insertUnorderedList: () => this.editor.dispatchCommand(INSERT_UNORDERED_LIST_COMMAND, undefined),
      insertOrderedList: () => this.editor.dispatchCommand(INSERT_ORDERED_LIST_COMMAND, undefined),
      createLink: () => this.editor.dispatchCommand(TOGGLE_LINK_COMMAND, String(value ?? '')),
      unlink: () => this.editor.dispatchCommand(TOGGLE_LINK_COMMAND, null),
      removeFormat: () => this.removeFormat(),
      insertImage: () => {
        const payload = typeof value === 'object' ? value : { src: value, alt: '' };
        const safeAlt = (payload.alt || '').replace(/"/g, '&quot;');
        this.insertHTML(`<figure class="media-large media-center"><img src="${payload.src || ''}" alt="${safeAlt}"><figcaption contenteditable="true"></figcaption></figure><p><br></p>`);
        return true;
      },
      insertRowAbove: () => this.editor.update(() => $insertTableRow(false)),
      insertRowBelow: () => this.editor.update(() => $insertTableRow(true)),
      insertColumnLeft: () => this.editor.update(() => $insertTableColumn(false)),
      insertColumnRight: () => this.editor.update(() => $insertTableColumn(true)),
      deleteRow: () => this.deleteRow(),
      deleteColumn: () => this.deleteColumn(),
      deleteTable: () => this.deleteSelectedTable(),
      setTableCellBackground: () => this.setTableCellBackground(value),
    };

    if (name === 'formatBlock') {
      return this.formatBlock(value);
    }

    const run = commands[name];

    if (!run) {
      console.warn(`Unsupported Lexical command: ${name}`);
      return false;
    }

    const visualEditor = document.querySelector('#visual-editor');
    const savedScrollTop = visualEditor ? visualEditor.scrollTop : 0;

    const result = run();

    if (visualEditor) {
      visualEditor.scrollTop = savedScrollTop;
      requestAnimationFrame(() => {
        visualEditor.scrollTop = savedScrollTop;
      });
    }

    return result;
  }

  deleteRow() {
    this.editor.update(() => {
      const selection = $getSelection();
      if ($isRangeSelection(selection)) {
        const anchorNode = selection.anchor.getNode();
        const rowNode = anchorNode.findMatchingParent((n) => n.getType() === 'tablerow');
        if (rowNode) rowNode.remove();
      }
    });
    return true;
  }

  deleteColumn() {
    this.editor.update(() => {
      const selection = $getSelection();
      if ($isRangeSelection(selection)) {
        const anchorNode = selection.anchor.getNode();
        const cellNode = anchorNode.findMatchingParent((n) => n.getType() === 'tablecell');
        const rowNode = anchorNode.findMatchingParent((n) => n.getType() === 'tablerow');
        if (cellNode && rowNode) {
          const colIdx = rowNode.getChildren().indexOf(cellNode);
          const tableNode = anchorNode.findMatchingParent((n) => n.getType() === 'table');
          if (tableNode && colIdx !== -1) {
            tableNode.getChildren().forEach((row) => {
              const cells = row.getChildren();
              if (cells[colIdx]) cells[colIdx].remove();
            });
          }
        }
      }
    });
    return true;
  }

  deleteSelectedTable() {
    this.editor.update(() => {
      const selection = $getSelection();
      if ($isRangeSelection(selection)) {
        const anchorNode = selection.anchor.getNode();
        const tableNode = anchorNode.findMatchingParent((n) => n.getType() === 'table');
        if (tableNode) {
          tableNode.remove();
          return;
        }
      }
      const selectedTableEl = document.querySelector('.visual-editor table.is-selected');
      if (selectedTableEl) {
        selectedTableEl.remove();
      }
    });
    return true;
  }

  setTableCellBackground(color) {
    if (!color) return false;
    const selectedCell = document.activeElement?.closest('td, th') || document.querySelector('.visual-editor td.is-selected, .visual-editor th.is-selected');
    if (selectedCell) {
      selectedCell.style.backgroundColor = color;
    } else {
      this.editor.update(() => {
        const selection = $getSelection();
        if ($isRangeSelection(selection)) {
          const anchorNode = selection.anchor.getNode();
          const cellNode = anchorNode.findMatchingParent((n) => n.getType() === 'tablecell');
          if (cellNode) {
            cellNode.setBackgroundColor(color);
          }
        }
      });
    }
    return true;
  }

  removeFormat() {
    if (!this.editor) {
      return false;
    }

    this.editor.update(() => {
      const selection = $getSelection();

      if ($isRangeSelection(selection)) {
        selection.getNodes().forEach((node) => {
          if ($isTextNode(node)) {
            node.setFormat(0);
            node.setStyle('');
          }
        });
      }
    });

    this.editor.focus();
    return true;
  }

  formatAlignment(alignment) {
    if (!this.editor) return false;

    const selectedMedia = document.querySelector('.visual-editor .erased-image-node--selected, .visual-editor .erased-video-node--selected, .visual-editor .is-selected, .visual-editor img.is-selected, .visual-editor table.is-selected, .visual-editor .post-video.is-selected, .visual-editor .video-embed.is-selected');
    if (selectedMedia) {
      const nodeKey = selectedMedia.dataset.nodeKey || '';
      if (nodeKey) {
        this.editor.update(() => {
          const node = $getNodeByKey(nodeKey);
          if ($isImageNode(node) || $isVideoNode(node)) {
            node.setAlignment(alignment);
          }
        });
      }
      selectedMedia.dataset.align = alignment;
      const selectedImage = selectedMedia.tagName === 'IMG'
        ? selectedMedia
        : selectedMedia.querySelector('img');
      if (selectedImage) {
        const figure = selectedImage.closest('figure');
        if (figure) {
          figure.classList.remove('media-left', 'media-center', 'media-right');
          figure.classList.add(`media-${alignment}`);
        }
      }
      return true;
    }

    return this.editor.dispatchCommand(FORMAT_ELEMENT_COMMAND, alignment);
  }

  formatBlock(value) {
    if (!this.editor) {
      return false;
    }

    const tag = String(value ?? '')
      .replace(/[<>]/g, '')
      .toLowerCase();

    this.editor.update(() => {
      const selection = $getSelection();

      if (!$isRangeSelection(selection)) {
        return;
      }

      if (tag === 'blockquote') {
        $setBlocksType(selection, () => $createQuoteNode());
        return;
      }

      if (['h1', 'h2', 'h3', 'h4'].includes(tag)) {
        $setBlocksType(selection, () => $createHeadingNode(tag));
        return;
      }

      $setBlocksType(selection, () => $createParagraphNode());
    });

    this.editor.focus();
    return true;
  }
}
