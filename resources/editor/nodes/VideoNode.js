import {
  $applyNodeReplacement,
  DecoratorNode,
} from 'lexical';
import {
  sanitizeAttributes,
  sanitizeHtmlFragment,
} from '../sanitizeHtml.js';

function attributesFromElement(element) {
  return Object.fromEntries(
    Array.from(element.attributes).map(({ name, value }) => [name, value]),
  );
}

function numericDimension(value) {
  const number = Number.parseInt(String(value ?? ''), 10);
  return Number.isFinite(number) && number > 0 ? number : null;
}

function styleWithDimensions(style, width, height) {
  const declarations = String(style ?? '')
    .split(';')
    .map((part) => part.trim())
    .filter(Boolean)
    .filter((part) => {
      const property = part.split(':', 1)[0]?.trim().toLowerCase();
      return (
        property !== 'width' &&
        property !== 'height' &&
        property !== 'max-width'
      );
    });

  if (width) {
    declarations.push(`width: ${width}px`);
  }
  if (height) {
    declarations.push(`height: ${height}px`);
  }
  declarations.push('max-width: 100%');

  return `${declarations.join('; ')};`;
}

function startVideoResize(event, wrapper, container, nodeKey, position = 'south-east') {
  if (!(event instanceof PointerEvent)) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();

  const startX = event.clientX;
  const startWidth = container.getBoundingClientRect().width || 560;
  const startHeight = container.getBoundingClientRect().height || 315;
  const aspectRatio = startHeight > 0 ? startWidth / startHeight : 16 / 9;

  const parentWidth = wrapper.closest('.erased-lexical-editor')?.getBoundingClientRect().width ?? 800;
  const minimumWidth = 160;
  const maximumWidth = Math.max(minimumWidth, parentWidth - 20);

  const isLeft = position.includes('west');

  const handle = event.currentTarget;
  if (handle instanceof HTMLElement) {
    try { handle.setPointerCapture(event.pointerId); } catch (e) {}
  }

  wrapper.classList.add('erased-video-node--resizing');

  const onMove = (moveEvent) => {
    moveEvent.preventDefault();
    moveEvent.stopPropagation();

    const deltaX = isLeft ? (startX - moveEvent.clientX) : (moveEvent.clientX - startX);
    const nextWidth = Math.round(
      Math.min(
        maximumWidth,
        Math.max(minimumWidth, startWidth + deltaX),
      ),
    );
    const nextHeight = Math.round(nextWidth / aspectRatio);

    wrapper.style.width = `${nextWidth}px`;
    wrapper.style.height = `${nextHeight}px`;
    container.style.width = `${nextWidth}px`;
    container.style.height = `${nextHeight}px`;

    wrapper.dataset.resizeWidth = String(nextWidth);
    wrapper.dataset.resizeHeight = String(nextHeight);

    const badge = wrapper.querySelector('.erased-image-size-badge');
    if (badge) {
      badge.textContent = `${nextWidth} × ${nextHeight} px`;
    }
  };

  const onEnd = (endEvent) => {
    endEvent?.preventDefault();
    endEvent?.stopPropagation();

    window.removeEventListener('pointermove', onMove, true);
    window.removeEventListener('pointerup', onEnd, true);
    window.removeEventListener('pointercancel', onEnd, true);

    wrapper.classList.remove('erased-video-node--resizing');

    const width = numericDimension(wrapper.dataset.resizeWidth);
    const height = numericDimension(wrapper.dataset.resizeHeight);

    delete wrapper.dataset.resizeWidth;
    delete wrapper.dataset.resizeHeight;

    if (!width || !height) {
      return;
    }

    wrapper.dispatchEvent(
      new CustomEvent('erased-video-resize', {
        bubbles: true,
        detail: { nodeKey, width, height },
      }),
    );
  };

  window.addEventListener('pointermove', onMove, { capture: true, passive: false });
  window.addEventListener('pointerup', onEnd, { capture: true, passive: false });
  window.addEventListener('pointercancel', onEnd, { capture: true, passive: false });
}

function createVideoResizeHandle(position, wrapper, container, nodeKey) {
  const handle = document.createElement('span');
  handle.className = `erased-image-node__resize-handle erased-image-node__resize-handle--${position}`;
  handle.setAttribute('role', 'presentation');
  handle.setAttribute('aria-hidden', 'true');
  handle.addEventListener('pointerdown', (event) =>
    startVideoResize(event, wrapper, container, nodeKey, position),
  );
  return handle;
}

export class VideoNode extends DecoratorNode {
  __tag;
  __attributes;
  __innerHTML;

  static getType() {
    return 'video-embed-node';
  }

  static clone(node) {
    return new VideoNode(
      node.__tag,
      { ...node.__attributes },
      node.__innerHTML,
      node.__key,
    );
  }

  static importJSON(serializedNode) {
    return $createVideoNode(
      serializedNode.tag,
      serializedNode.attributes ?? {},
      serializedNode.innerHTML ?? '',
    );
  }

  static importDOM() {
    return {
      div: (element) => {
        if (!element.classList.contains('video-embed') && !element.querySelector('iframe, video')) {
          return null;
        }
        const attrs = attributesFromElement(element);
        return {
          conversion: () => ({
            node: $createVideoNode(element.tagName, attrs, element.innerHTML),
          }),
          priority: 6,
        };
      },
      figure: (element) => {
        if (!element.classList.contains('post-video') && !element.querySelector('video, iframe')) {
          return null;
        }
        const attrs = attributesFromElement(element);
        const cls = element.getAttribute('class') || '';
        if (cls.includes('media-left')) attrs['data-align'] = 'left';
        else if (cls.includes('media-right')) attrs['data-align'] = 'right';
        else if (cls.includes('media-center')) attrs['data-align'] = 'center';

        return {
          conversion: () => ({
            node: $createVideoNode('FIGURE', attrs, element.innerHTML),
          }),
          priority: 6,
        };
      },
    };
  }

  constructor(tag = 'DIV', attributes = {}, innerHTML = '', key) {
    super(key);
    this.__tag = String(tag || 'DIV').toUpperCase();
    this.__attributes = sanitizeAttributes(this.__tag, attributes);
    this.__innerHTML = sanitizeHtmlFragment(innerHTML);
  }

  createDOM() {
    const wrapper = document.createElement('div');
    const nodeKey = this.getKey();

    wrapper.className = 'erased-video-node video-embed';
    wrapper.dataset.lexicalVideo = 'true';
    wrapper.dataset.nodeKey = nodeKey;
    if (this.__attributes['data-align']) {
      wrapper.dataset.align = this.__attributes['data-align'];
    }
    if (this.__attributes.width) {
      wrapper.style.width = `${this.__attributes.width}px`;
    }
    if (this.__attributes.height) {
      wrapper.style.height = `${this.__attributes.height}px`;
    }
    wrapper.contentEditable = 'false';

    const inner = document.createElement('div');
    inner.className = 'erased-video-node__inner';
    inner.innerHTML = sanitizeHtmlFragment(this.__innerHTML);

    const updateBadge = () => {
      const rect = wrapper.getBoundingClientRect();
      const badge = wrapper.querySelector('.erased-image-size-badge');
      if (badge) {
        badge.textContent = `${Math.round(rect.width || 560)} × ${Math.round(rect.height || 315)} px`;
      }
    };

    wrapper.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();

      const root = wrapper.closest('.erased-lexical-editor');
      root?.querySelectorAll('.erased-video-node--selected, .erased-image-node--selected').forEach((el) => {
        if (el !== wrapper) el.classList.remove('erased-video-node--selected', 'erased-image-node--selected');
      });

      wrapper.classList.add('erased-video-node--selected');
      updateBadge();
    });

    const badge = document.createElement('span');
    badge.className = 'erased-image-size-badge';
    badge.textContent = 'Resizing video…';

    wrapper.append(
      inner,
      badge,
      createVideoResizeHandle('north-west', wrapper, wrapper, nodeKey),
      createVideoResizeHandle('north-east', wrapper, wrapper, nodeKey),
      createVideoResizeHandle('south-west', wrapper, wrapper, nodeKey),
      createVideoResizeHandle('south-east', wrapper, wrapper, nodeKey),
    );

    return wrapper;
  }

  updateDOM(previousNode, dom) {
    const prevAlign = previousNode.__attributes['data-align'];
    const nextAlign = this.__attributes['data-align'];

    if (prevAlign !== nextAlign) {
      if (nextAlign) {
        dom.dataset.align = nextAlign;
      } else {
        delete dom.dataset.align;
      }
    }

    const prevWidth = previousNode.__attributes.width;
    const nextWidth = this.__attributes.width;
    if (prevWidth !== nextWidth && nextWidth) {
      dom.style.width = `${nextWidth}px`;
    }

    const prevHeight = previousNode.__attributes.height;
    const nextHeight = this.__attributes.height;
    if (prevHeight !== nextHeight && nextHeight) {
      dom.style.height = `${nextHeight}px`;
    }

    return JSON.stringify(previousNode.__attributes) !== JSON.stringify(this.__attributes) ||
           previousNode.__innerHTML !== this.__innerHTML;
  }

  decorate() {
    return null;
  }

  setDimensions(width, height) {
    const writable = this.getWritable();
    writable.__attributes = {
      ...writable.__attributes,
      width: String(width),
      height: String(height),
      style: styleWithDimensions(writable.__attributes.style, width, height),
    };
    return writable;
  }

  setAlignment(alignment) {
    const writable = this.getWritable();
    writable.__attributes = {
      ...writable.__attributes,
      'data-align': alignment,
    };
    return writable;
  }

  exportDOM() {
    const container = document.createElement(this.__tag === 'FIGURE' ? 'figure' : 'div');
    container.className = this.__tag === 'FIGURE' ? 'post-video' : 'video-embed';
    for (const [name, value] of Object.entries(this.__attributes)) {
      container.setAttribute(name, String(value));
    }
    const align = this.__attributes['data-align'];
    if (align) {
      container.dataset.align = align;
      if (this.__tag === 'FIGURE') {
        container.className = `post-video media-${align}`;
      }
    }
    container.innerHTML = sanitizeHtmlFragment(this.__innerHTML);
    return { element: container };
  }

  exportJSON() {
    return {
      ...super.exportJSON(),
      type: 'video-embed-node',
      version: 1,
      tag: this.__tag,
      attributes: { ...this.__attributes },
      innerHTML: this.__innerHTML,
    };
  }
}

export function $createVideoNode(tag = 'DIV', attributes = {}, innerHTML = '') {
  return $applyNodeReplacement(new VideoNode(tag, attributes, innerHTML));
}

export function $isVideoNode(node) {
  return node instanceof VideoNode;
}
