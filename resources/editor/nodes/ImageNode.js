import {
  $applyNodeReplacement,
  DecoratorNode,
} from "lexical";
import { sanitizeAttributes } from "../sanitizeHtml.js";

function attributesFromElement(element) {
  return Object.fromEntries(
    Array.from(element.attributes).map(({ name, value }) => [
      name,
      value,
    ]),
  );
}

function numericDimension(value) {
  const number = Number.parseInt(String(value ?? ""), 10);

  return Number.isFinite(number) && number > 0
    ? number
    : null;
}

function styleWithDimensions(style, width, height) {
  const declarations = String(style ?? "")
    .split(";")
    .map((part) => part.trim())
    .filter(Boolean)
    .filter((part) => {
      const property = part.split(":", 1)[0]?.trim().toLowerCase();

      return property !== "width" &&
        property !== "height" &&
        property !== "max-width";
    });

  if (width) {
    declarations.push(`width: ${width}px`);
  }

  if (height) {
    declarations.push(`height: ${height}px`);
  }

  declarations.push("max-width: 100%");

  return `${declarations.join("; ")};`;
}

function createImageElement(attributes) {
  const image = document.createElement("img");
  const cleanAttributes = sanitizeAttributes("IMG", attributes);

  for (const [name, value] of Object.entries(cleanAttributes)) {
    image.setAttribute(name, String(value));
  }

  image.setAttribute("draggable", "false");
  image.setAttribute("contenteditable", "false");

  return image;
}

function startResize(event, wrapper, image, nodeKey, position = 'south-east') {
  if (!(event instanceof PointerEvent)) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();

  const startX = event.clientX;
  const startWidth = image.getBoundingClientRect().width || image.width || 300;
  const startHeight = image.getBoundingClientRect().height || image.height || 200;
  const aspectRatio = startHeight > 0 ? startWidth / startHeight : 1.5;

  const parentWidth = wrapper.closest('.erased-lexical-editor')?.getBoundingClientRect().width ?? 800;

  const minimumWidth = 80;
  const maximumWidth = Math.max(minimumWidth, parentWidth - 30);

  const isLeft = position.includes('west');

  const handle = event.currentTarget;

  if (handle instanceof HTMLElement) {
    try { handle.setPointerCapture(event.pointerId); } catch (e) {}
  }

  wrapper.classList.add("erased-image-node--resizing");

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

    image.style.width = `${nextWidth}px`;
    image.style.height = `${nextHeight}px`;
    image.style.maxWidth = "100%";
    wrapper.style.width = `${nextWidth}px`;
    wrapper.style.height = `${nextHeight}px`;

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

    window.removeEventListener("pointermove", onMove, true);
    window.removeEventListener("pointerup", onEnd, true);
    window.removeEventListener("pointercancel", onEnd, true);

    wrapper.classList.remove("erased-image-node--resizing");

    const width = numericDimension(wrapper.dataset.resizeWidth);
    const height = numericDimension(wrapper.dataset.resizeHeight);

    delete wrapper.dataset.resizeWidth;
    delete wrapper.dataset.resizeHeight;

    if (!width || !height) {
      return;
    }

    wrapper.dispatchEvent(
      new CustomEvent("erased-image-resize", {
        bubbles: true,
        detail: {
          nodeKey,
          width,
          height,
        },
      }),
    );
  };

  window.addEventListener("pointermove", onMove, { capture: true, passive: false });
  window.addEventListener("pointerup", onEnd, { capture: true, passive: false });
  window.addEventListener("pointercancel", onEnd, { capture: true, passive: false });
}

function createResizeHandle(position, wrapper, image, nodeKey) {
  const handle = document.createElement("span");

  handle.className =
    `erased-image-node__resize-handle ` +
    `erased-image-node__resize-handle--${position}`;

  handle.setAttribute("role", "presentation");
  handle.setAttribute("aria-hidden", "true");
  handle.addEventListener(
    "pointerdown",
    (event) => startResize(event, wrapper, image, nodeKey, position),
  );

  return handle;
}

export class ImageNode extends DecoratorNode {
  __attributes;

  static getType() {
    return "image";
  }

  static clone(node) {
    return new ImageNode(
      { ...node.__attributes },
      node.__key,
    );
  }

  static importJSON(serializedNode) {
    return $createImageNode(
      serializedNode.attributes ?? {},
    );
  }

  static importDOM() {
    return {
      img: () => ({
        conversion: (element) => ({
          node: $createImageNode(attributesFromElement(element)),
        }),
        priority: 5,
      }),
      figure: () => ({
        conversion: (element) => {
          const img = element.querySelector('img');
          if (!img) return null;
          const attrs = attributesFromElement(img);
          const cls = element.getAttribute('class') || '';
          if (cls.includes('media-left')) attrs['data-align'] = 'left';
          else if (cls.includes('media-right')) attrs['data-align'] = 'right';
          else if (cls.includes('media-center')) attrs['data-align'] = 'center';
          return {
            node: $createImageNode(attrs),
          };
        },
        priority: 5,
      }),
    };
  }

  constructor(attributes = {}, key) {
    super(key);
    this.__attributes = sanitizeAttributes("IMG", attributes);
  }

  createDOM() {
    const wrapper = document.createElement("span");
    const image = createImageElement(this.__attributes);
    const nodeKey = this.getKey();

    wrapper.className = "erased-image-node";
    wrapper.dataset.lexicalImage = "true";
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
    wrapper.contentEditable = "false";

    const updateBadge = () => {
      const rect = image.getBoundingClientRect();
      const badge = wrapper.querySelector('.erased-image-size-badge');
      if (badge) {
        badge.textContent = `${Math.round(rect.width || image.width || 300)} × ${Math.round(rect.height || image.height || 200)} px`;
      }
    };

    wrapper.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();

      const root = wrapper.closest(".erased-lexical-editor");
      root?.querySelectorAll(".erased-image-node--selected").forEach((el) => {
        if (el !== wrapper) el.classList.remove("erased-image-node--selected");
      });

      wrapper.classList.add("erased-image-node--selected");
      updateBadge();
    });

    const badge = document.createElement("span");
    badge.className = "erased-image-size-badge";
    badge.textContent = "Resizing…";

    wrapper.append(
      image,
      badge,
      createResizeHandle("north-west", wrapper, image, nodeKey),
      createResizeHandle("north-east", wrapper, image, nodeKey),
      createResizeHandle("south-west", wrapper, image, nodeKey),
      createResizeHandle("south-east", wrapper, image, nodeKey),
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

    return JSON.stringify(previousNode.__attributes) !==
      JSON.stringify(this.__attributes);
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
      style: styleWithDimensions(
        writable.__attributes.style,
        width,
        height,
      ),
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
    const element = createImageElement(this.__attributes);
    const align = this.__attributes['data-align'];
    if (align) {
      const figure = document.createElement('figure');
      figure.className = `media-${align}`;
      figure.appendChild(element);
      return { element: figure };
    }
    return {
      element,
    };
  }

  exportJSON() {
    return {
      ...super.exportJSON(),
      type: "image",
      version: 1,
      attributes: { ...this.__attributes },
    };
  }
}

export function $createImageNode(attributes = {}) {
  return $applyNodeReplacement(
    new ImageNode(attributes),
  );
}

export function $isImageNode(node) {
  return node instanceof ImageNode;
}
