import {
  $applyNodeReplacement,
  DecoratorNode,
} from 'lexical';
import {
  sanitizeAttributes,
  sanitizeHtmlFragment,
} from '../sanitizeHtml.js';

const SUPPORTED_TAGS = new Set([
  'DIV',
  'FIGURE',
  'HR',
  'IFRAME',
  'VIDEO',
  'IMG',
]);

function attributesFromElement(element) {
  return Object.fromEntries(
    Array.from(element.attributes).map(({ name, value }) => [name, value]),
  );
}

function buildElement(tag, attributes, innerHTML) {
  const element = document.createElement(tag.toLowerCase());
  const cleanAttributes = sanitizeAttributes(tag, attributes);

  for (const [name, value] of Object.entries(cleanAttributes)) {
    element.setAttribute(name, String(value));
  }

  if (tag !== 'HR' && tag !== 'IMG') {
    element.innerHTML = sanitizeHtmlFragment(innerHTML);
  }

  element.dataset.lexicalRawHtml = 'true';
  element.contentEditable = 'false';

  return element;
}

function isSupportedDiv(element) {
  return (
    element.classList.contains('video-embed') ||
    element.querySelector('iframe, video') !== null
  );
}

function convertElement(element) {
  return {
    node: $createRawHtmlNode(
      element.tagName,
      sanitizeAttributes(element.tagName, attributesFromElement(element)),
      sanitizeHtmlFragment(element.innerHTML),
    ),
  };
}

export class RawHtmlNode extends DecoratorNode {
  __tag;
  __attributes;
  __innerHTML;

  static getType() {
    return 'raw-html';
  }

  static clone(node) {
    return new RawHtmlNode(
      node.__tag,
      { ...node.__attributes },
      node.__innerHTML,
      node.__key,
    );
  }

  static importJSON(serializedNode) {
    return $createRawHtmlNode(
      serializedNode.tag,
      serializedNode.attributes ?? {},
      serializedNode.innerHTML ?? '',
    );
  }

  static importDOM() {
    return {
      div: (element) => {
        if (!isSupportedDiv(element)) {
          return null;
        }

        return {
          conversion: convertElement,
          priority: 4,
        };
      },

      hr: () => ({
        conversion: convertElement,
        priority: 4,
      }),

      iframe: () => ({
        conversion: convertElement,
        priority: 4,
      }),

      video: () => ({
        conversion: convertElement,
        priority: 4,
      }),

      img: () => ({
        conversion: convertElement,
        priority: 4,
      }),

      figure: () => ({
        conversion: convertElement,
        priority: 4,
      }),
    };
  }

  constructor(tag, attributes = {}, innerHTML = '', key) {
    super(key);
    this.__tag = String(tag || 'DIV').toUpperCase();
    this.__attributes = sanitizeAttributes(this.__tag, attributes);
    this.__innerHTML = sanitizeHtmlFragment(innerHTML);
  }

  createDOM() {
    return buildElement(
      this.__tag,
      this.__attributes,
      this.__innerHTML,
    );
  }

  updateDOM(previousNode, dom) {
    if (previousNode.__tag !== this.__tag) {
      return true;
    }

    const replacement = buildElement(
      this.__tag,
      this.__attributes,
      this.__innerHTML,
    );

    dom.replaceWith(replacement);
    return false;
  }

  decorate() {
    return null;
  }

  exportDOM() {
    const element = buildElement(
      this.__tag,
      this.__attributes,
      this.__innerHTML,
    );

    element.removeAttribute('data-lexical-raw-html');
    element.removeAttribute('contenteditable');

    return { element };
  }

  exportJSON() {
    return {
      ...super.exportJSON(),
      type: 'raw-html',
      version: 1,
      tag: this.__tag,
      attributes: { ...this.__attributes },
      innerHTML: this.__innerHTML,
    };
  }
}

export function $createRawHtmlNode(tag, attributes = {}, innerHTML = '') {
  return $applyNodeReplacement(
    new RawHtmlNode(tag, attributes, innerHTML),
  );
}

export function $isRawHtmlNode(node) {
  return node instanceof RawHtmlNode;
}
