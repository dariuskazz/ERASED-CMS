function b(n, ...t) {
  const e = new URL("https://lexical.dev/docs/error"), r = new URLSearchParams();
  r.append("code", n);
  for (const i of t) r.append("v", i);
  throw e.search = r.toString(), Error(`Minified Lexical error #${n}; visit ${e.toString()} for the full message or use the non-minified dev environment for full errors and additional helpful warnings.`);
}
function Tc(n, ...t) {
  const e = new URL("https://lexical.dev/docs/error"), r = new URLSearchParams();
  r.append("code", n);
  for (const i of t) r.append("v", i);
  e.search = r.toString(), console.warn(`Minified Lexical warning #${n}; visit ${e.toString()} for the full message or use the non-minified dev environment for full errors and additional helpful warnings.`);
}
const se = typeof window < "u" && window.document !== void 0 && window.document.createElement !== void 0, yd = se && "documentMode" in document ? document.documentMode : null, ne = se && /Mac|iPod|iPhone|iPad/.test(navigator.platform), Fe = se && /^(?!.*Seamonkey)(?=.*Firefox).*/i.test(navigator.userAgent), Wn = !(!se || !("InputEvent" in window) || yd) && "getTargetRanges" in new window.InputEvent("input"), Pe = se && /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream, wc = se && /Android/.test(navigator.userAgent), Ir = se && /Version\/[\d.]+.*Safari/.test(navigator.userAgent) && !wc, kc = se && /^(?=.*Chrome).*/i.test(navigator.userAgent), Si = se && wc && kc, Rr = se && /AppleWebKit\/[\d.]+/.test(navigator.userAgent) && ne && !kc, xd = 0, Sd = 1, Cd = 2, Io = 1, Ro = 2, zo = 4, Wo = 8, vd = 16, go = 32, po = 64, ws = 128, bd = 1, Td = 2, wd = 3, kd = 4, Nd = 5, Ed = 6, Sr = Ir || Pe || Rr ? " " : "​", Ve = `

`, ks = Fe ? " " : Sr, Ie = { bold: 1, capitalize: 1024, code: 16, highlight: ws, italic: 2, lowercase: 256, strikethrough: 4, subscript: 32, superscript: 64, underline: 8, uppercase: 512 }, Od = { directionless: 1, unmergeable: 2 }, Ci = { center: 2, end: 6, justify: 4, left: 1, right: 3, start: 5 }, Ad = { [Td]: "center", [Ed]: "end", [kd]: "justify", [bd]: "left", [wd]: "right", [Nd]: "start" }, Md = { normal: 0, segmented: 2, token: 1 }, Dd = { [xd]: "normal", [Cd]: "segmented", [Sd]: "token" }, ml = "$config";
function yl() {
  return st()._blockCursorElement;
}
function Ld(n) {
  return n !== null && n.nodeType === 1 && n.hasAttribute("data-lexical-slot");
}
let Nc = class fi {
  element;
  before;
  after;
  constructor(t, e, r) {
    this.element = t, this.before = e || null, this.after = r || null;
  }
  withBefore(t) {
    return new fi(this.element, t, this.after);
  }
  withAfter(t) {
    return new fi(this.element, this.before, t);
  }
  withElement(t) {
    return this.element === t ? this : new fi(t, this.before, this.after);
  }
  insertChild(t) {
    const e = this.getInsertionAnchor();
    return e !== null && e.parentElement !== this.element && b(357), this.element.insertBefore(t, e), this;
  }
  removeChild(t) {
    return t.parentElement !== this.element && b(358), this.element.removeChild(t), this;
  }
  replaceChild(t, e) {
    return e.parentElement !== this.element && b(359), this.element.replaceChild(t, e), this;
  }
  getFirstChild() {
    const t = this.getFirstChildAnchor(), e = t ? t.nextSibling : this.element.firstChild;
    return e === this.getInsertionAnchor() ? null : e;
  }
  getFirstChildAnchor() {
    return this.after;
  }
  resolveLeafPosition(t, e, r) {
    if (this.element === t) return e === t && r === 0 ? "before" : "after";
    const i = xl(t, this.element);
    if (i === null) return "after";
    const o = Array.prototype.indexOf.call(t.childNodes, i);
    if (o < 0) return "after";
    if (e === t) return r <= o ? "before" : "after";
    const s = xl(t, e);
    if (s === null) return "after";
    const l = Array.prototype.indexOf.call(t.childNodes, s);
    return l >= 0 && l <= o ? "before" : "after";
  }
  getInsertionAnchor() {
    return this.before;
  }
};
function xl(n, t) {
  let e = t;
  for (; e !== null && e.parentNode !== n; ) e = e.parentNode;
  return e;
}
class On extends Nc {
  withBefore(t) {
    return new On(this.element, t, this.after);
  }
  withAfter(t) {
    return new On(this.element, this.before, t);
  }
  withElement(t) {
    return this.element === t ? this : new On(t, this.before, this.after);
  }
  getInsertionAnchor() {
    return super.getInsertionAnchor() || this.getManagedLineBreak();
  }
  getFirstChildAnchor() {
    let t = super.getFirstChildAnchor(), e = t ? t.nextSibling : this.element.firstChild;
    for (; Ld(e); ) t = e, e = e.nextSibling;
    const r = t ? t.nextSibling : this.element.firstChild;
    return r !== null && r === yl() ? r : t;
  }
  getManagedLineBreak() {
    return this.element.__lexicalLineBreak || null;
  }
  setManagedLineBreak(t) {
    if (this.element.__lexicalLastChildKind = t, t === null) this.removeManagedLineBreak();
    else {
      const e = t === "decorator" && (Rr || Pe || Ir);
      this.insertManagedLineBreak(e);
    }
  }
  removeManagedLineBreak() {
    const t = this.getManagedLineBreak();
    if (t) {
      const e = this.element, r = t.nodeName === "IMG" ? t.nextSibling : null;
      r && e.removeChild(r), e.removeChild(t), e.__lexicalLineBreak = void 0;
    }
  }
  insertManagedLineBreak(t) {
    const e = this.getManagedLineBreak();
    if (e) {
      if (t === (e.nodeName === "IMG")) return;
      this.removeManagedLineBreak();
    }
    const r = this.element, i = this.before, o = V().createElement("br");
    if (o.setAttribute("data-lexical-managed-linebreak", "true"), r.insertBefore(o, i), t) {
      const s = V().createElement("img");
      s.setAttribute("data-lexical-managed-linebreak", "true"), s.style.setProperty("display", "inline", "important"), s.style.setProperty("border", "0px", "important"), s.style.setProperty("margin", "0px", "important"), s.alt = "", r.insertBefore(s, o), r.__lexicalLineBreak = s;
    } else r.__lexicalLineBreak = o;
  }
  getFirstChildOffset() {
    const t = this.getFirstChild(), e = this.getInsertionAnchor();
    let r = 0;
    for (let i = this.element.firstChild; i !== null && i !== t && i !== e; i = i.nextSibling) r++;
    return r;
  }
  resolveChildIndex(t, e, r, i) {
    if (r === this.element) {
      const a = this.getFirstChildOffset(), c = yl(), u = this.element.childNodes, f = Math.min(i, u.length);
      let d = 0;
      for (let h = a; h < f; h++) u[h] !== c && d++;
      return [t, Math.min(d, t.getChildrenSize())];
    }
    const o = Sl(e, r);
    o.push(i);
    const s = Sl(e, this.element);
    let l = t.getIndexWithinParent();
    for (let a = 0; a < s.length; a++) {
      const c = o[a], u = s[a];
      if (c === void 0 || c < u) break;
      if (c > u) {
        l += 1;
        break;
      }
    }
    return [t.getParentOrThrow(), l];
  }
}
function Sl(n, t) {
  const e = [];
  let r = t;
  for (; r !== n && r !== null; r = r.parentNode) {
    let i = 0;
    for (let o = r.previousSibling; o !== null; o = o.previousSibling) i++;
    e.push(i);
  }
  return r !== n && b(225), e.reverse();
}
let Ec;
try {
  Ec = "0.48.0+prod.esm";
} catch {
}
const Oc = Ec ?? '"<unknown>+source"';
let sr = class {
  _front = /* @__PURE__ */ new Set();
  _back = /* @__PURE__ */ new Set();
  _cache;
  get size() {
    return this._front.size + this._back.size;
  }
  addBack(t) {
    return delete this._cache, this._front.has(t) || this._back.add(t), this;
  }
  addFront(t) {
    return delete this._cache, this._back.has(t) || this._front.add(t), this;
  }
  delete(t) {
    return delete this._cache, this._front.delete(t) || this._back.delete(t);
  }
  toArray() {
    const t = Array.from(this._front).reverse();
    for (const e of this._back) t.push(e);
    return t;
  }
  toReadonlyArray() {
    return this._cache = this._cache || this.toArray(), this._cache;
  }
  [Symbol.iterator]() {
    return this.toReadonlyArray()[Symbol.iterator]();
  }
};
const Ue = null;
function Ac(n, t = 1e3) {
  return n instanceof Ko ? n.clone() : n.size < t ? new Map(n) : new Ko().init(new Map(n), void 0, n.size);
}
let Ko = class Mc {
  _mutable = !1;
  _old = void 0;
  _nursery = void 0;
  _size = 0;
  clone() {
    return this._mutable = !1, new Mc().init(this._old, this._nursery, this._size);
  }
  init(t, e, r) {
    return this._old = t, this._nursery = e, this._size = r, this;
  }
  get size() {
    return this._size;
  }
  has(t) {
    return this.get(t) !== void 0;
  }
  getWithTombstone(t) {
    const e = this._nursery && this._nursery.get(t);
    return e !== void 0 ? e : this._old && this._old.get(t);
  }
  get(t) {
    const e = this.getWithTombstone(t);
    return e === Ue ? void 0 : e;
  }
  shouldCompact() {
    return this._nursery !== void 0 && 2 * this._nursery.size > this._size;
  }
  getNursery() {
    return this._mutable && this._nursery || (this.compact(), this._nursery = new Map(this._nursery), this._mutable = !0), this._nursery;
  }
  compact(t = !1) {
    if (this._nursery && this._nursery.size > 0 && (t || this.shouldCompact())) {
      const e = new Map(this._old);
      for (const [r, i] of this._nursery) i !== Ue ? e.set(r, i) : e.delete(r);
      this._old = e, this._nursery = void 0;
    }
    return this._mutable = !1, this;
  }
  set(t, e) {
    const r = this.getWithTombstone(t);
    if (r === e) return this;
    const i = this.getNursery();
    return r !== Ue && r !== void 0 || (this._size++, r === Ue && i.delete(t)), i.set(t, e), this;
  }
  delete(t) {
    const e = this.has(t);
    return e && (this.getNursery().set(t, Ue), this._size--), e;
  }
  getOrInsert(t, e) {
    const r = this.get(t);
    return r !== void 0 ? r : (this.set(t, e), e);
  }
  getOrInsertComputed(t, e) {
    const r = this.get(t);
    if (r !== void 0) return r;
    const i = e(t);
    return this.set(t, i), i;
  }
  clear() {
    this._mutable = !1, this._old = void 0, this._nursery = void 0, this._size = 0;
  }
  *keys() {
    for (const t of this.entries()) yield t[0];
  }
  *values() {
    for (const t of this.entries()) yield t[1];
  }
  *entries() {
    const t = this._nursery, e = this._old;
    if (e) for (const r of e) {
      const i = r[0], o = t ? t.get(i) : void 0;
      o !== Ue && (o !== void 0 && (r[1] = o), yield r);
    }
    if (t) for (const r of t) r[1] === Ue || e && e.has(r[0]) || (yield r);
  }
  forEach(t, e) {
    e !== void 0 && (t = t.bind(e));
    for (const [r, i] of this.entries()) t(i, r, this);
  }
  get [Symbol.toStringTag]() {
    return "GenMap";
  }
  [Symbol.iterator]() {
    return this.entries();
  }
};
function vi(n, t, e, r, i, o) {
  if (S(n)) {
    let s = n.getFirstChild();
    for (; s !== null; ) {
      const l = s.__key;
      s.__parent === t && ((S(s) || Se(s) && s.__slots !== null) && vi(s, l, e, r, i, o), e.has(l) || o.delete(l), i.push(l)), s = s.getNextSibling();
    }
  }
  for (const s of Se(n) && n.__slots !== null ? n.__slots.values() : []) {
    const l = r.get(s);
    l !== void 0 && nn(l) && l.__slotHost === t && ((S(l) || Se(l) && l.__slots !== null) && vi(l, s, e, r, i, o), e.has(s) || o.delete(s), i.push(s));
  }
}
let Bo = !1, Ns = 0;
function $d(n) {
  Ns = n.timeStamp;
}
function _o(n, t, e) {
  const r = n.nodeName === "BR", i = t.__lexicalLineBreak;
  return i && (n === i || r && n.previousSibling === i) || r && er(n, e) !== void 0;
}
function Fd(n, t, e) {
  const r = Dt(It(e)), i = r && Qt(r, e._rootElement);
  let o = null, s = null;
  i !== null && i.anchorNode === n && (o = i.anchorOffset, s = i.focusOffset);
  const l = n.nodeValue;
  l !== null && Ks(t, l, o, s, !1);
}
function Pd(n, t, e) {
  if (w(n)) {
    const r = n.anchor.getNode();
    if (r.is(e) && n.format !== r.getFormat()) return !1;
  }
  return Ht(t) && e.isAttached();
}
function Id(n, t, e) {
  for (let r = n; r && !_f(r); r = dn(r)) {
    const i = er(r, t);
    if (i !== void 0) {
      const o = Z(i, e);
      if (o) return W(o) || !F(r) ? void 0 : [r, o];
    }
  }
}
function Dc(n, t, e) {
  Bo = !0;
  const r = performance.now() - Ns > 100;
  try {
    Gt(n, () => {
      const i = M() || (function(f) {
        return f.read("latest", () => {
          const d = M();
          return d !== null ? d.clone() : null;
        });
      })(n), o = /* @__PURE__ */ new Map(), s = n._editorState, l = n._blockCursorElement;
      let a = !1, c = "";
      for (let f = 0; f < t.length; f++) {
        const d = t[f], h = d.type, _ = d.target, m = Id(_, n, s);
        if (!m) continue;
        const [p, g] = m;
        if (h === "characterData") r && O(g) && Ht(_) && Pd(i, _, g) && Fd(_, g, n);
        else if (h === "childList") {
          a = !0;
          const y = d.addedNodes;
          for (let E = 0; E < y.length; E++) {
            const k = y[E], N = tf(k), C = k.parentNode;
            if (!(C == null || k === l || N !== null || _o(k, C, n) || n._slotsUsed && F(k) && k.hasAttribute("data-lexical-slot") || _f(k))) {
              if (Fe) {
                const T = (F(k) ? k.innerText : null) || k.nodeValue;
                T && (c += T);
              }
              C.removeChild(k);
            }
          }
          const x = d.removedNodes, v = x.length;
          if (v > 0) {
            let E = 0;
            for (let k = 0; k < v; k++) {
              const N = x[k];
              (_o(N, _, n) || l === N) && (_.appendChild(N), E++);
            }
            v !== E && o.set(p, g);
          }
        }
      }
      if (o.size > 0) for (const [f, d] of o) d.reconcileObservedMutation(f, n);
      const u = e.takeRecords();
      if (u.length > 0) {
        for (let f = 0; f < u.length; f++) {
          const d = u[f], h = d.addedNodes, _ = d.target;
          for (let m = 0; m < h.length; m++) {
            const p = h[m], g = p.parentNode;
            g == null || p.nodeName !== "BR" || _o(p, _, n) || g.removeChild(p);
          }
        }
        e.takeRecords();
      }
      i !== null && (a && kt(i), Fe && lf(n) && i.insertRawText(c));
    });
  } finally {
    Bo = !1;
  }
}
function Lc(n) {
  const t = n._observer;
  t !== null && Dc(n, t.takeRecords(), t);
}
function $c(n) {
  (function(t) {
    Ns === 0 && It(t).addEventListener("textInput", $d, !0);
  })(n), n._observer = new MutationObserver((t, e) => {
    Dc(n, t, e);
  });
}
const Cl = "latest";
let Rd = class {
  key;
  parse;
  unparse;
  isEqual;
  defaultValue;
  resetOnCopyNode;
  constructor(t, e) {
    this.key = t, this.parse = e.parse.bind(e), this.unparse = (e.unparse || Bd).bind(e), this.isEqual = (e.isEqual || Object.is).bind(e), this.defaultValue = this.parse(void 0), this.resetOnCopyNode = e.resetOnCopyNode || !1;
  }
};
function Fc(n, t) {
  return new Rd(n, t);
}
function Pc(n, t, e = Cl) {
  const r = (e === Cl ? n.getLatest() : n).__state;
  return r ? r.getValue(t) : t.defaultValue;
}
function zd(n, t, e) {
  let r;
  if (xt(), typeof e == "function") {
    const o = n.getLatest(), s = Pc(o, t);
    if (r = e(s), t.isEqual(s, r)) return o;
  } else r = e;
  const i = n.getWritable();
  return Rc(i).updateFromKnown(t, r), i;
}
function Wd(n) {
  const t = /* @__PURE__ */ new Map(), e = /* @__PURE__ */ new Set();
  for (const { ownNodeConfig: r } of qs(typeof n == "function" ? n : n.replace)) if (r && r.stateConfigs) for (const i of r.stateConfigs) {
    let o;
    "stateConfig" in i ? (o = i.stateConfig, i.flat && e.add(o.key)) : o = i, t.set(o.key, o);
  }
  return { flatKeys: e, sharedConfigMap: t };
}
const vl = /* @__PURE__ */ new Set(["__proto__", "constructor", "prototype"]);
let Kd = class Ic {
  node;
  knownState;
  unknownState;
  sharedNodeState;
  size;
  constructor(t, e, r = void 0, i = /* @__PURE__ */ new Map(), o = void 0) {
    this.node = t, this.sharedNodeState = e, this.unknownState = r, this.knownState = i;
    const { sharedConfigMap: s } = this.sharedNodeState, l = o !== void 0 ? o : (function(a, c, u) {
      let f = u.size;
      if (c) for (const d in c) {
        const h = a.get(d);
        h && u.has(h) || f++;
      }
      return f;
    })(s, r, i);
    this.size = l;
  }
  getValue(t) {
    const e = this.knownState.get(t);
    if (e !== void 0) return e;
    this.sharedNodeState.sharedConfigMap.set(t.key, t);
    let r = t.defaultValue;
    if (this.unknownState && t.key in this.unknownState) {
      const i = this.unknownState[t.key];
      i !== void 0 && (r = t.parse(i)), this.updateFromKnown(t, r);
    }
    return r;
  }
  getInternalState() {
    return [this.unknownState, this.knownState];
  }
  toJSON() {
    const t = { ...this.unknownState }, e = {};
    for (const [r, i] of this.knownState) r.isEqual(i, r.defaultValue) ? delete t[r.key] : t[r.key] = r.unparse(i);
    for (const r of this.sharedNodeState.flatKeys) r in t && (e[r] = t[r], delete t[r]);
    return bl(t) && (e.$ = t), e;
  }
  getWritable(t) {
    if (this.node === t) return this;
    const { sharedNodeState: e, unknownState: r } = this, i = new Map(this.knownState);
    return new Ic(t, e, (function(o, s, l) {
      let a;
      if (l) for (const [c, u] of Object.entries(l)) {
        if (vl.has(c)) continue;
        const f = o.get(c);
        f ? s.has(f) || s.set(f, f.parse(u)) : (a = a || {}, a[c] = u);
      }
      return a;
    })(e.sharedConfigMap, i, r), i, this.size);
  }
  resetOnCopyNode() {
    for (const t of this.knownState.keys()) t.resetOnCopyNode && this.knownState.set(t, t.defaultValue);
    return this;
  }
  updateFromKnown(t, e) {
    const r = t.key;
    this.sharedNodeState.sharedConfigMap.set(r, t);
    const { knownState: i, unknownState: o } = this;
    i.has(t) || o && r in o || (o && (delete o[r], this.unknownState = bl(o)), this.size++), i.set(t, e);
  }
  updateFromUnknown(t, e) {
    if (vl.has(t)) return;
    const r = this.sharedNodeState.sharedConfigMap.get(t);
    r ? this.updateFromKnown(r, r.parse(e)) : (this.unknownState = this.unknownState || {}, t in this.unknownState || this.size++, this.unknownState[t] = e);
  }
  updateFromJSON(t) {
    const { knownState: e } = this;
    for (const r of e.keys()) e.set(r, r.defaultValue);
    if (this.size = e.size, this.unknownState = void 0, t) for (const [r, i] of Object.entries(t)) this.updateFromUnknown(r, i);
  }
};
function Rc(n) {
  const t = n.getWritable(), e = t.__state ? t.__state.getWritable(t) : new Kd(t, zc(t));
  return t.__state = e, e;
}
function zc(n) {
  return n.__state ? n.__state.sharedNodeState : Yu(st(), n.getType()).sharedNodeState;
}
function bl(n) {
  if (n) for (const t in n) return n;
}
function Bd(n) {
  return n;
}
function Tl(n, t, e) {
  for (const [r, i] of t.knownState) {
    if (n.has(r.key)) continue;
    n.add(r.key);
    const o = e ? e.getValue(r) : r.defaultValue;
    if (o !== i && !r.isEqual(o, i)) return !0;
  }
  return !1;
}
function wl(n, t, e) {
  const { unknownState: r } = t, i = e ? e.unknownState : void 0;
  if (r) {
    for (const [o, s] of Object.entries(r))
      if (!n.has(o) && (n.add(o), s !== (i ? i[o] : void 0)))
        return !0;
  }
  return !1;
}
function kl(n, t) {
  const e = n.__state;
  return e && e.node === n ? e.getWritable(t) : e;
}
function Nl(n, t) {
  const e = n.__mode, r = n.__format, i = n.__style, o = t.__mode, s = t.__format, l = t.__style, a = n.__state, c = t.__state;
  return (e === null || e === o) && (r === null || r === s) && (i === null || i === l) && (n.__state === null || a === c || (function(u, f) {
    if (u === f) return !0;
    const d = /* @__PURE__ */ new Set();
    return !(u && Tl(d, u, f) || f && Tl(d, f, u) || u && wl(d, u, f) || f && wl(d, f, u));
  })(a, c));
}
function El(n, t) {
  const e = n.mergeWithSibling(t), r = j()._normalizedNodes;
  return r.add(n.__key), r.add(t.__key), e;
}
function Ol(n) {
  let t, e, r = n;
  if (r.__text !== "" || !r.isSimpleText() || r.isUnmergeable()) {
    for (; (t = r.getPreviousSibling()) !== null && O(t) && t.isSimpleText() && !t.isUnmergeable(); ) {
      if (t.__text !== "") {
        if (Nl(t, r)) {
          r = El(t, r);
          break;
        }
        break;
      }
      t.remove();
    }
    for (; (e = r.getNextSibling()) !== null && O(e) && e.isSimpleText() && !e.isUnmergeable(); ) {
      if (e.__text !== "") {
        if (Nl(r, e)) {
          r = El(r, e);
          break;
        }
        break;
      }
      e.remove();
    }
  } else r.remove();
}
function wn(n) {
  return Al(n.anchor), Al(n.focus), n;
}
function Al(n) {
  for (; n.type === "element"; ) {
    const t = n.getNode(), e = n.offset;
    let r, i;
    if (e === t.getChildrenSize() ? (r = t.getChildAtIndex(e - 1), i = !0) : (r = t.getChildAtIndex(e), i = !1), O(r)) {
      n.set(r.__key, i ? r.getTextContentSize() : 0, "text", !0);
      break;
    }
    if (!S(r)) break;
    n.set(r.__key, i ? r.getChildrenSize() : 0, "element", !0);
  }
}
const Cr = /* @__PURE__ */ Symbol.for("@lexical/CachedTextSize");
function Ml(n, t) {
  return Ho.read(() => {
    let e = 0, r = n;
    for (let i = 0; i < t && r !== null; i++) {
      const o = re.get(r);
      if (o === void 0 && b(345, r), S(o)) {
        const s = it.get(r);
        if (s !== void 0 && S(s) && s.__parent !== o.__parent) e += o.getTextContentSize();
        else {
          const l = Qe.get(r), a = l && l.__lexicalTextContent;
          typeof a != "string" && b(346, o.getType()), e += a.length;
        }
        i < t - 1 && !o.isInline() && (e += 2);
      } else {
        const s = o[Cr];
        s === void 0 && b(347, o.getType(), r), e += s;
      }
      r = o.__next;
    }
    return e;
  }, { editor: H });
}
function Wc(n) {
  S(n) || n[Cr] === void 0 && (n[Cr] = O(n) ? n.__text.length : n.getTextContentSize());
}
const Ud = 4;
let vr, H, br, z = "", ft = null, wt = null, vt = null;
function le() {
  return { firstTextKey: vt, format: ft, style: wt };
}
function qt(n) {
  n.firstTextKey !== null && (ft = n.format, wt = n.style, vt = n.firstTextKey);
}
function Uo(n) {
  if (vt !== null) return;
  const t = n.__lexicalFirstTextKey;
  if (t === void 0 && b(348), t === null) return;
  const e = it.get(t);
  O(e) && (ft = e.getFormat(), wt = e.getStyle(), vt = t);
}
let qi, gr, pr, re, Ho, it, Qe, Jo, Tr, tn, me = !1, bi = !1;
function De(n, t) {
  const e = re.get(n), r = it.has(n);
  if (t !== null) {
    const i = Xo(n);
    i.parentNode === t && t.removeChild(i);
  }
  if (!r) {
    if (H._keyToDOMMap.delete(n), S(e)) {
      const i = Ki(e, re);
      jo(i, 0, i.length - 1, null);
    }
    if (e !== void 0) {
      for (const i of Rt(e).values()) {
        const o = Vo(i);
        De(i, null), o !== null && o.remove();
      }
      Bs(Tr, br, qi, e, "destroyed");
    }
  }
}
function jo(n, t, e, r) {
  for (let i = t; i <= e; ++i) {
    const o = n[i];
    o !== void 0 && De(o, r);
  }
}
function He(n, t) {
  n.setProperty("text-align", t);
}
const Hd = "40px";
function Kc(n, t) {
  const e = vr.theme.indent;
  if (typeof e == "string") {
    const r = n.classList.contains(e);
    t > 0 && !r ? n.classList.add(e) : t < 1 && r && n.classList.remove(e);
  }
  n.style.setProperty("padding-inline-start", t === 0 ? "" : `calc(${t} * var(--lexical-indent-base-value, ${Hd}))`);
}
function Bc(n, t) {
  const e = n.style;
  t === 0 ? He(e, "") : t === 1 ? He(e, "left") : t === 2 ? He(e, "center") : t === 3 ? He(e, "right") : t === 4 ? He(e, "justify") : t === 5 ? He(e, "start") : t === 6 && He(e, "end");
}
function qo(n, t) {
  const e = (function(r) {
    const i = r.__dir;
    if (i !== null) return i;
    if (ut(r)) return null;
    const o = r.getParent();
    return o === null || ot(o) && o.__dir === null ? "auto" : null;
  })(t);
  e !== null ? n.dir = e : n.removeAttribute("dir");
}
function Uc(n) {
  const t = V().createElement("div");
  return t.setAttribute("data-lexical-slot", n), t.style.display = "none", t;
}
function Hc(n, t, e) {
  t || n.contentEditable === "false" ? lg(e, H) : e.removeAttribute("contenteditable");
}
function Dl(n, t, e) {
  const r = z, i = le();
  z = "";
  let o = "";
  const s = W(n);
  for (const [l, a] of e) {
    const c = Uc(l);
    Hc(t, s, c), t.appendChild(c), z = "";
    const u = le();
    Le(a, te(n, c, H)), qt(u), Jc(n, l, t, c), o += z;
  }
  return qt(i), z = r, o;
}
function Rt(n) {
  return Se(n) && n.__slots !== null ? n.__slots : yf;
}
function Jc(n, t, e, r) {
  const i = tn.$getSlotTargetElement(n, t, e, H);
  i !== null && (r.parentElement !== i && i.appendChild(r), r.style.display = "");
}
function Vo(n) {
  const t = Qe.get(n);
  return t !== void 0 ? t.parentElement : null;
}
function Ll(n, t, e) {
  const r = Rt(n), i = Rt(t);
  for (const [u, f] of r) if (!i.has(u)) {
    const d = Vo(f);
    De(f, null), d !== null && d.remove();
  }
  const o = z, s = le();
  let l = "", a = null;
  const c = W(t);
  for (const [u, f] of i) {
    const d = r.get(u);
    let h = d !== void 0 ? Vo(d) : null;
    z = "";
    const _ = le();
    if (h === null) {
      h = Uc(u);
      let m = null;
      for (const p of e.children) if (!p.hasAttribute("data-lexical-slot")) {
        m = p;
        break;
      }
      e.insertBefore(h, m), Le(f, te(t, h, H));
    } else d === f ? _e(f, h) : (d !== void 0 && De(d, h), Le(f, te(t, h, H)));
    if (qt(_), Hc(e, c, h), Jc(t, u, e, h), l += z, h.parentElement === e) {
      const m = a === null ? e.firstChild : a.nextSibling;
      m !== h && e.insertBefore(h, m), a = h;
    }
  }
  return qt(s), z = o, l;
}
function Le(n, t) {
  const e = it.get(n);
  if (e === void 0 && b(60), t !== null) {
    const i = re.get(n);
    if (i !== void 0) {
      const o = Qe.get(n);
      if (o !== void 0) {
        const s = nn(i) ? i.__slotHost : null, l = nn(e) ? e.__slotHost : null, a = i.__parent !== e.__parent || s !== l, c = l !== null && o.parentElement !== t.element;
        if (a || c) return t.insertChild(o), _e(n, t.element);
      }
    }
  }
  const r = tn.$createDOM(e, H);
  if ((function(i, o, s) {
    const l = s._keyToDOMMap;
    ef(o, s, i), l.set(i, o);
  })(n, r, H), O(e) ? r.setAttribute("data-lexical-text", "true") : W(e) && (r.setAttribute("data-lexical-decorator", "true"), pf(r, { captureSelection: !0 })), S(e)) {
    const i = e.__indent, o = e.__size;
    qo(r, e), i !== 0 && Kc(r, i);
    const s = Rt(e), l = s.size > 0 ? Dl(e, r, s) : "";
    if (o === 0) r.__lexicalTextContent = l, r.__lexicalFirstTextKey = null, z += l, s.size > 0 && (r.__lexicalSlotTextLength = l.length);
    else {
      const c = z, u = o - 1;
      if (Go(Ki(e, it), e, 0, u, te(e, r, H)), l !== "") {
        const f = r.__lexicalTextContent || "";
        r.__lexicalTextContent = l + f, z = c + l + f;
      }
      s.size > 0 && (r.__lexicalSlotTextLength = l.length);
    }
    const a = e.__format;
    a !== 0 && Bc(r, a), e.isInline() || jc(null, e, r);
  } else {
    const i = e.getTextContent();
    if (W(e)) {
      const o = e.decorate(H, vr);
      o !== null && qc(n, o), r.contentEditable = "false";
      const s = Rt(e);
      s.size > 0 && Dl(e, r, s);
    }
    z += i;
  }
  return t !== null && t.insertChild(r), tn.$decorateDOM(e, null, r, H), Wc(e), Bs(Tr, br, qi, e, "created"), r;
}
function Go(n, t, e, r, i) {
  const o = z, s = le();
  z = "", ft = null, wt = null, vt = null;
  let l = e;
  for (; l <= r; ++l) {
    const c = le();
    Le(n[l], i);
    const u = it.get(n[l]);
    u !== null && O(u) ? ft === null && (ft = u.getFormat(), wt = u.getStyle(), vt = u.__key) : S(u) && l < r && !u.isInline() && (z += Ve), qt(c);
  }
  const a = H._keyToDOMMap.get(t.__key);
  a === void 0 && b(349, t.__key), a.__lexicalTextContent = z, a.__lexicalFirstTextKey = vt, z = o + z, qt(s);
}
function jc(n, t, e) {
  const r = te(t, e, H), i = r.element.__lexicalLastChildKind ?? null, o = (function(s, l) {
    if (s) {
      const a = s.__last;
      if (a) {
        const c = l.get(a);
        if (c) return Pt(c) ? "line-break" : W(c) && c.isInline() ? "decorator" : null;
      }
      return Rt(s).size > 0 ? null : "empty";
    }
    return null;
  })(t, it);
  i !== o && r.setManagedLineBreak(o);
}
function Jd(n, t, e) {
  var r;
  ft = null, wt = null, vt = null, (function(i, o, s) {
    const l = z, a = i.__size, c = o.__size;
    z = "";
    const u = s.element, f = H._keyToDOMMap.get(o.__key);
    f === void 0 && b(351, o.__key);
    const d = c - a;
    if (!me && Math.abs(d) <= 1 && a >= Ud && i.__first === o.__first && (d !== 0 || !H._cloneNotNeeded.has(i.__key))) {
      const h = f.__lexicalTextContent, _ = Jo.get(i.__key);
      if (!me && typeof h == "string" && _ !== void 0) {
        const m = (function(p, g) {
          const y = g.size;
          if (y === 0 || y >= p.__size) return null;
          let x = p.__last, v = null, E = 0;
          for (; x !== null && E < y; ) {
            if (!g.has(x)) return null;
            v = x;
            const k = it.get(x);
            if (k === void 0) return null;
            x = k.__prev, E++;
          }
          return E !== y || x !== null && g.has(x) ? null : v;
        })(o, _);
        if (m !== null) {
          const p = _.size;
          if (d === 0) {
            const g = Ml(m, p);
            let y = m, x = 0;
            for (; y !== null && x < p; ) {
              const C = it.get(y);
              if (C === void 0) break;
              const T = le();
              _e(y, u), O(C) && ft === null && (ft = C.getFormat(), wt = C.getStyle(), vt = C.__key), qt(T), y = C.__next, x++;
            }
            let v = "";
            for (y = m, x = 0; y !== null && x < p; ) {
              const C = it.get(y);
              if (C === void 0) break;
              let T;
              if (S(C)) {
                const A = H._keyToDOMMap.get(y), D = A && A.__lexicalTextContent;
                typeof D != "string" && b(352, C.getType()), T = D;
              } else T = C.getTextContent();
              v += T, x < p - 1 && S(C) && !C.isInline() && (v += Ve), y = C.__next, x++;
            }
            const E = f.__lexicalSlotTextLength || 0, k = E > 0 ? h.slice(E) : h, N = k.slice(0, k.length - g) + v;
            return f.__lexicalTextContent = N, z = l + N, void $l(o, f, _);
          }
          if ((function(g, y, x, v, E, k, N, C) {
            if (C !== 1 && C !== -1 || N !== (C === 1 ? 2 : 1)) return !1;
            const A = N - C;
            let D = g.__last;
            for (let tt = 0; tt < A - 1; tt++) {
              if (D === null) return !1;
              const gt = re.get(D);
              if (gt === void 0) return !1;
              D = gt.__prev;
            }
            if (D === null) return !1;
            const I = it.get(k), B = re.get(D);
            if (I === void 0 || B === void 0 || I.__prev !== B.__prev) return !1;
            const P = [];
            let J = k;
            for (let tt = 0; tt < N; tt++) {
              if (J === null) return !1;
              P.push(J);
              const gt = it.get(J);
              J = gt ? gt.__next : null;
            }
            const rt = [];
            J = D;
            for (let tt = 0; tt < A; tt++) {
              if (J === null) return !1;
              rt.push(J);
              const gt = re.get(J);
              J = gt ? gt.__next : null;
            }
            const lt = new Set(rt), yt = new Set(P), _t = [];
            let at = 0, ct = 0;
            for (; at < A && ct < N; ) if (P[ct] === rt[at]) _t.push({ key: P[ct], kind: "reconcile" }), at++, ct++;
            else if (yt.has(rt[at])) {
              if (lt.has(P[ct])) return !1;
              _t.push({ key: P[ct], kind: "create", nextIndex: ct }), ct++;
            } else _t.push({ key: rt[at], kind: "destroy" }), at++;
            for (; at < A; ) _t.push({ key: rt[at++], kind: "destroy" });
            for (; ct < N; ) _t.push({ key: P[ct], kind: "create", nextIndex: ct }), ct++;
            const de = Ml(D, A);
            for (const tt of _t) {
              const gt = le();
              if (tt.kind === "reconcile") _e(tt.key, x.element);
              else if (tt.kind === "destroy") De(tt.key, x.element);
              else {
                let jt = null;
                for (let mn = tt.nextIndex + 1; mn < N; mn++) {
                  const or = H._keyToDOMMap.get(P[mn]);
                  if (or !== void 0) {
                    jt = or;
                    break;
                  }
                }
                Le(tt.key, x.withBefore(jt ?? x.before));
              }
              if (tt.kind !== "destroy") {
                const jt = it.get(tt.key);
                jt && O(jt) && ft === null && (ft = jt.getFormat(), wt = jt.getStyle(), vt = jt.__key);
              }
              qt(gt);
            }
            let Jt = "";
            for (let tt = 0; tt < N; tt++) {
              const gt = it.get(P[tt]);
              if (gt === void 0) return !1;
              let jt;
              if (S(gt)) {
                const mn = H._keyToDOMMap.get(P[tt]), or = mn && mn.__lexicalTextContent;
                typeof or != "string" && b(350, gt.getType()), jt = or;
              } else jt = gt.getTextContent();
              Jt += jt, tt < N - 1 && S(gt) && !gt.isInline() && (Jt += Ve);
            }
            const Ee = v.__lexicalSlotTextLength || 0, ir = Ee > 0 ? E.slice(Ee) : E;
            return v.__lexicalTextContent = ir.slice(0, ir.length - de) + Jt, !0;
          })(i, 0, s, f, h, m, p, d)) {
            const g = f.__lexicalTextContent;
            return typeof g != "string" && b(353), z = l + g, void $l(o, f, _);
          }
        }
      }
      if (d === 0) {
        let m = i.__first, p = 0;
        for (; m !== null; ) {
          const g = it.get(m);
          if (g === void 0) break;
          const y = me || pr.has(m) || gr.has(m), x = le();
          if (y) _e(m, u);
          else {
            let v, E;
            if (S(g)) {
              E = Qe.get(m);
              const k = E && E.__lexicalTextContent;
              typeof k != "string" && b(354, g.getType()), v = k;
            } else v = g.getTextContent();
            z += v, E !== void 0 && Uo(E);
          }
          O(g) ? ft === null && (ft = g.getFormat(), wt = g.getStyle(), vt = g.__key) : S(g) && p < c - 1 && !g.isInline() && (z += Ve), qt(x), m = g.__next, p++;
        }
        return f.__lexicalTextContent = z, f.__lexicalFirstTextKey = vt, void (z = l + z);
      }
    }
    if (a === 1 && c === 1) {
      const h = i.__first, _ = o.__first;
      if (h === _) _e(h, u);
      else {
        const p = Xo(h), g = Le(_, null);
        try {
          p.parentNode === u ? u.replaceChild(g, p) : s.insertChild(g);
        } catch (y) {
          if (typeof y == "object" && y != null) {
            const x = `${y.toString()} Parent: ${u.tagName}, new child: {tag: ${g.tagName} key: ${_}}, old child: {tag: ${p.tagName}, key: ${h}}.`;
            throw new Error(x);
          }
          throw y;
        }
        De(h, null);
      }
      const m = it.get(_);
      O(m) && ft === null && (ft = m.getFormat(), wt = m.getStyle(), vt = m.__key);
    } else {
      const h = Ki(i, re), _ = Ki(o, it);
      if (h.length !== a && b(227), _.length !== c && b(228), a === 0) c !== 0 && Go(_, o, 0, c - 1, s);
      else if (c === 0) {
        if (a !== 0) {
          const m = s.after == null && s.before == null && Rt(o).size === 0 && s.element.__lexicalLineBreak == null;
          jo(h, 0, a - 1, m ? null : u), m && (u.textContent = "");
        }
      } else (function(m, p, g, y, x, v) {
        const E = y - 1, k = x - 1;
        let N, C, T = v.getFirstChild(), A = 0, D = 0;
        for (; A <= E && D <= k; ) {
          const P = p[A], J = g[D], rt = le();
          if (P === J) T = mo(_e(J, v.element)), A++, D++;
          else {
            if (C === void 0 && (C = Fl(g, D)), N === void 0) N = Fl(p, A);
            else if (!N.has(P)) {
              A++, qt(rt);
              continue;
            }
            if (!C.has(P)) {
              T = mo(Xo(P)), De(P, v.element), A++, N.delete(P), qt(rt);
              continue;
            }
            if (N.has(J)) {
              const yt = Hn(H, J);
              yt !== T && v.withBefore(T ?? v.before).insertChild(yt), T = mo(_e(J, v.element)), A++, D++;
            } else Le(J, v.withBefore(T ?? v.before)), D++;
          }
          const lt = it.get(J);
          lt !== null && O(lt) ? ft === null && (ft = lt.getFormat(), wt = lt.getStyle(), vt = lt.__key) : S(lt) && D <= k && !lt.isInline() && (z += Ve), qt(rt);
        }
        const I = A > E, B = D > k;
        if (I && !B) {
          const P = g[k + 1], J = P === void 0 ? null : H.getElementByKey(P);
          Go(g, m, D, k, v.withBefore(J ?? v.before));
        } else B && !I && jo(p, A, E, v.element);
      })(o, h, _, a, c, s);
    }
    f.__lexicalTextContent = z, f.__lexicalFirstTextKey = vt, z = l + z;
  })(n, t, te(t, e, H)), ot(t) || (r = t, ft == null || ft === r.__textFormat || bi || r.setTextFormat(ft), (function(i) {
    wt == null || wt === i.__textStyle || bi || i.setTextStyle(wt);
  })(t));
}
function $l(n, t, e) {
  const r = t.__lexicalFirstTextKey;
  if (r != null) {
    const i = n.__key;
    let o = r;
    for (; o !== null; ) {
      const s = it.get(o);
      if (s === void 0) {
        o = null;
        break;
      }
      if (s.__parent === i) break;
      o = s.__parent;
    }
    if (o !== null && !e.has(o)) {
      const s = it.get(r);
      if (O(s)) return ft = s.getFormat(), void (wt = s.getStyle());
    }
  }
  t.__lexicalFirstTextKey = vt;
}
function _e(n, t) {
  const e = re.get(n);
  let r = it.get(n);
  e !== void 0 && r !== void 0 || b(61);
  const i = me || pr.has(n) || gr.has(n), o = Hn(H, n);
  if (e === r && !i) {
    let s;
    if (S(e)) {
      const l = o.__lexicalTextContent;
      typeof l != "string" && b(355, e.getType()), s = l, Uo(o);
    } else s = e.getTextContent();
    return z += s, o;
  }
  if (e !== r && i && Bs(Tr, br, qi, r, "updated"), tn.$updateDOM(r, e, o, H)) {
    const s = Le(n, null);
    return t === null && b(62), t.replaceChild(s, o), De(n, null), s;
  }
  if (S(e)) {
    S(r) || b(334, n);
    const s = r.__indent;
    (me || s !== e.__indent) && Kc(o, s);
    const l = r.__format;
    (me || l !== e.__format) && Bc(o, l);
    const a = i && (Rt(r).size > 0 || Rt(e).size > 0) ? Ll(e, r, o) : "";
    if (i) {
      const c = z;
      if (Jd(e, r, o), ut(r) || r.isInline() || jc(0, r, o), a !== "") {
        const u = o.__lexicalTextContent || "";
        o.__lexicalTextContent = a + u, z = c + a + u, o.__lexicalSlotTextLength = a.length;
      } else (Rt(r).size > 0 || Rt(e).size > 0) && (o.__lexicalSlotTextLength = 0);
    } else {
      const c = o.__lexicalTextContent;
      typeof c != "string" && b(356, e.getType()), z += c, Uo(o);
    }
    if ((me || r.__dir !== e.__dir || r.__parent !== e.__parent) && (qo(o, r), ut(r) && !me)) for (const c of r.getChildren()) S(c) && qo(Hn(H, c.getKey()), c);
  } else {
    const s = r.getTextContent();
    if (W(r)) {
      const l = r.decorate(H, vr);
      l !== null && qc(n, l), i && (Rt(r).size > 0 || Rt(e).size > 0) && Ll(e, r, o);
    }
    z += s;
  }
  if (!bi && ut(r)) {
    const s = r.getLatest();
    if (s.__cachedText !== z) {
      const l = s.getWritable();
      l.__cachedText = z, r = l;
    }
  }
  return tn.$decorateDOM(r, e, o, H), Wc(r), o;
}
function qc(n, t) {
  let e = H._pendingDecorators;
  const r = H._decorators;
  if (e === null) {
    if (r[n] === t) return;
    e = nf(H);
  }
  e[n] = t;
}
function mo(n) {
  let t = n.nextSibling;
  return t !== null && t === H._blockCursorElement && (t = t.nextSibling), t;
}
function Fl(n, t) {
  const e = /* @__PURE__ */ new Set();
  for (let r = t; r < n.length; r++) e.add(n[r]);
  return e;
}
function jd(n, t, e, r, i, o) {
  z = "", ft = null, wt = null, vt = null, me = r === 2, H = e, vr = e._config, tn = e._config.dom || Pi, br = e._nodes, qi = H._listeners.mutation, gr = i, pr = o, re = n._nodeMap, Ho = n, it = t._nodeMap, bi = t._readOnly, Qe = Ac(e._keyToDOMMap), Jo = (function() {
    const l = /* @__PURE__ */ new Map(), a = (c) => {
      for (const u of c) {
        const f = it.get(u);
        if (f === void 0) continue;
        const d = f.__parent;
        if (d === null) continue;
        let h = l.get(d);
        h === void 0 && (h = /* @__PURE__ */ new Set(), l.set(d, h)), h.add(u);
      }
    };
    return a(gr.keys()), a(pr), l;
  })();
  const s = /* @__PURE__ */ new Map();
  return Tr = s, _e("root", null), H = void 0, br = void 0, gr = void 0, pr = void 0, re = void 0, Ho = void 0, it = void 0, vr = void 0, Qe = void 0, Jo = void 0, Tr = void 0, tn = Pi, s;
}
function Xo(n) {
  const t = Qe.get(n);
  return t === void 0 && b(75, n), t;
}
function $(n) {
  return { type: n };
}
const Vc = /* @__PURE__ */ $("SELECTION_CHANGE_COMMAND"), qd = /* @__PURE__ */ $("SELECTION_INSERT_CLIPBOARD_NODES_COMMAND"), Gc = /* @__PURE__ */ $("CLICK_COMMAND"), Xc = /* @__PURE__ */ $("BEFORE_INPUT_COMMAND"), Yc = /* @__PURE__ */ $("INPUT_COMMAND"), Zc = /* @__PURE__ */ $("COMPOSITION_START_COMMAND"), Qc = /* @__PURE__ */ $("COMPOSITION_END_COMMAND"), Ge = /* @__PURE__ */ $("DELETE_CHARACTER_COMMAND"), An = /* @__PURE__ */ $("INSERT_LINE_BREAK_COMMAND"), wr = /* @__PURE__ */ $("INSERT_PARAGRAPH_COMMAND"), Mn = /* @__PURE__ */ $("CONTROLLED_TEXT_INSERTION_COMMAND"), Es = /* @__PURE__ */ $("PASTE_COMMAND"), di = /* @__PURE__ */ $("REMOVE_TEXT_COMMAND"), kr = /* @__PURE__ */ $("DELETE_WORD_COMMAND"), Nr = /* @__PURE__ */ $("DELETE_LINE_COMMAND"), Ot = /* @__PURE__ */ $("FORMAT_TEXT_COMMAND"), Vd = /* @__PURE__ */ $("SET_TEXT_FORMAT_COMMAND"), Vi = /* @__PURE__ */ $("UNDO_COMMAND"), Gi = /* @__PURE__ */ $("REDO_COMMAND"), tu = /* @__PURE__ */ $("KEYDOWN_COMMAND"), eu = /* @__PURE__ */ $("KEY_ARROW_RIGHT_COMMAND"), nu = /* @__PURE__ */ $("MOVE_TO_END"), ru = /* @__PURE__ */ $("KEY_ARROW_LEFT_COMMAND"), iu = /* @__PURE__ */ $("MOVE_TO_START"), ou = /* @__PURE__ */ $("KEY_ARROW_UP_COMMAND"), su = /* @__PURE__ */ $("KEY_ARROW_DOWN_COMMAND"), Ti = /* @__PURE__ */ $("KEY_ENTER_COMMAND"), lu = /* @__PURE__ */ $("KEY_SPACE_COMMAND"), Os = /* @__PURE__ */ $("KEY_BACKSPACE_COMMAND"), au = /* @__PURE__ */ $("KEY_ESCAPE_COMMAND"), cu = /* @__PURE__ */ $("KEY_DELETE_COMMAND"), uu = /* @__PURE__ */ $("KEY_TAB_COMMAND"), Gd = /* @__PURE__ */ $("INSERT_TAB_COMMAND"), Xd = /* @__PURE__ */ $("INDENT_CONTENT_COMMAND"), Pl = /* @__PURE__ */ $("OUTDENT_CONTENT_COMMAND"), fu = /* @__PURE__ */ $("DROP_COMMAND"), du = /* @__PURE__ */ $("FORMAT_ELEMENT_COMMAND"), hu = /* @__PURE__ */ $("DRAGSTART_COMMAND"), gu = /* @__PURE__ */ $("DRAGOVER_COMMAND"), Yd = /* @__PURE__ */ $("DRAGEND_COMMAND"), Xi = /* @__PURE__ */ $("COPY_COMMAND"), As = /* @__PURE__ */ $("CUT_COMMAND"), pu = /* @__PURE__ */ $("SELECT_ALL_COMMAND"), Zd = /* @__PURE__ */ $("CLEAR_EDITOR_COMMAND"), Qd = /* @__PURE__ */ $("CLEAR_HISTORY_COMMAND"), Yr = /* @__PURE__ */ $("CAN_REDO_COMMAND"), Zr = /* @__PURE__ */ $("CAN_UNDO_COMMAND"), th = /* @__PURE__ */ $("FOCUS_COMMAND"), eh = /* @__PURE__ */ $("BLUR_COMMAND"), nh = /* @__PURE__ */ $("KEY_MODIFIER_COMMAND");
function rh(n) {
  const t = /* @__PURE__ */ new Map();
  return { dispose() {
    for (const e of t.values()) e.dispose();
    t.clear();
  }, register(e, r) {
    let i = t.get(e);
    i === void 0 && (i = { dispose: n(e, r), holders: /* @__PURE__ */ new Set() }, t.set(e, i));
    const o = () => {
      const s = t.get(e);
      s && s.holders.delete(o) && s.holders.size === 0 && (t.delete(e), s.dispose());
    };
    return i.holders.add(o), o;
  } };
}
function ih(n, t, e, r) {
  return n.addEventListener(t, e, r), n.removeEventListener.bind(n, t, e, r);
}
const he = Object.freeze({}), Yo = [["keydown", function(n, t) {
  const e = t._inputState;
  e.lastKeyDownTimeStamp = n.timeStamp, e.lastKeyCode = n.key, n.key !== "Backspace" && Er(e), !t.isComposing() && L(t, tu, n);
}], ["pointerdown", function(n, t) {
  const e = df(n), r = n.pointerType;
  nr(e) && r !== "touch" && r !== "pen" && n.button === 0 && Gt(t, () => {
    Wi(e, t) || (t._inputState.isSelectionChangeFromMouseDown = !0);
  });
}], ["compositionstart", function(n, t) {
  L(t, Zc, n);
}], ["compositionend", function(n, t) {
  const e = t._inputState;
  Fe ? e.compositionPhase = "ending-firefox" : Pe || !Ir && !Rr ? L(t, Qc, n) : (e.compositionPhase = "ending-safari", e.compositionEndData = n.data);
}], ["input", function(n, t) {
  n.stopPropagation();
  const e = t._inputState;
  Er(e), Gt(t, () => {
    xu(n, t) || t.dispatchCommand(Yc, n);
  }, { event: n }), e.unprocessedBeforeInputData = null;
}], ["click", function(n, t) {
  Gt(t, () => {
    const e = M(), r = Dt(It(t)), i = Xn();
    if (r) {
      if (w(e)) {
        const o = e.anchor, s = o.getNode();
        o.type === "element" && o.offset === 0 && e.isCollapsed() && !ut(s) && pt().getChildrenSize() === 1 && s.getTopLevelElementOrThrow().isEmpty() && i !== null && e.is(i) && (r.removeAllRanges(), e.dirty = !0);
      } else if (n.pointerType === "touch" || n.pointerType === "pen") {
        const o = Qt(r, t._rootElement).anchorNode;
        (F(o) || Ht(o)) && kt(Ds(i, r, t, n));
      }
    }
    L(t, Gc, n);
  });
}], ["cut", he], ["copy", he], ["dragstart", he], ["dragover", he], ["dragend", he], ["paste", he], ["focus", he], ["blur", he], ["drop", he]];
Wn && Yo.push(["beforeinput", (n, t) => (function(e, r) {
  const i = e.inputType;
  i === "deleteCompositionText" || Fe && lf(r) || i !== "insertCompositionText" && Gt(r, () => {
    xu(e, r) || L(r, Xc, e);
  }, { event: e });
})(n, t)]);
const Zo = /* @__PURE__ */ new WeakMap(), wi = /* @__PURE__ */ new WeakMap(), oh = rh((n) => (n.addEventListener("selectionchange", Kl), () => n.removeEventListener("selectionchange", Kl)));
function _u(n, t, e, r, i, o) {
  const s = n.anchor, l = n.focus, a = s.getNode(), c = j();
  let u;
  if (o !== void 0) u = o;
  else {
    const m = Dt(It(c));
    u = m !== null ? Qt(m, c._rootElement) : null;
  }
  const f = u !== null ? u.anchorNode : null, d = s.key, h = c.getElementByKey(d), _ = e.length;
  return d !== l.key || !O(a) || (!i && (!Wn || c._inputState.lastBeforeInputInsertTextTimeStamp < r + 50) || a.isDirty() && _ < 2 || rf(e)) && s.offset !== l.offset && !a.isComposing() || zt(a) || a.isDirty() && _ > 1 || (i || !Wn) && h !== null && !a.isComposing() && f !== $e(a, h, c) || u !== null && t !== null && (!t.collapsed || t.startContainer !== u.anchorNode || t.startOffset !== u.anchorOffset) || !a.isComposing() && (a.getFormat() !== n.format || a.getStyle() !== n.style) || (function(m, p) {
    if (p.isSegmented()) return !0;
    if (!m.isCollapsed()) return !1;
    const g = m.anchor.offset, y = p.getParentOrThrow(), x = Xe(p);
    return g === 0 ? !p.canInsertTextBefore() || !y.canInsertTextBefore() && !p.isComposing() || x || (function(v) {
      const E = v.getPreviousSibling();
      return (O(E) || S(E) && E.isInline()) && !E.canInsertTextAfter();
    })(p) : g === p.getTextContentSize() && (!p.canInsertTextAfter() || !y.canInsertTextAfter() && !p.isComposing() || x);
  })(n, a);
}
function Il(n, t) {
  return Ht(n) && n.nodeValue !== null && t !== 0 && t !== n.nodeValue.length;
}
function Rl(n, t, e) {
  const { anchorNode: r, anchorOffset: i, focusNode: o, focusOffset: s } = Qt(n, t._rootElement), l = t._inputState;
  l.isSelectionChangeFromDOMUpdate && (l.isSelectionChangeFromDOMUpdate = !1, Il(r, i) && Il(o, s) && !l.postDeleteSelectionToRestore) || Gt(t, () => {
    if (!e) return void kt(null);
    if (!Wr(t, r, o)) return;
    let a = M();
    if (l.postDeleteSelectionToRestore && w(a) && a.isCollapsed()) {
      const c = a.anchor, u = l.postDeleteSelectionToRestore.anchor;
      (c.key === u.key && c.offset === u.offset + 1 || c.offset === 1 && u.getNode().is(c.getNode().getPreviousSibling())) && (a = l.postDeleteSelectionToRestore.clone(), kt(a));
    }
    if (l.postDeleteSelectionToRestore = null, w(a)) {
      const c = a.anchor, u = c.getNode();
      if (a.isCollapsed()) {
        n.type === "Range" && r === o && (a.dirty = !0);
        const f = It(t).event, d = f ? f.timeStamp : performance.now(), { format: h, style: _, offset: m, key: p, timeStamp: g } = l.collapsedSelectionFormat, y = pt(), x = t.isComposing() === !1 && y.getTextContent() === "";
        if (d < g + 200 && c.offset === m && c.key === p) hi(a, h, _);
        else if (c.type === "text") O(u) || b(141), mu(a, u);
        else if (c.type === "element" && !x) {
          S(u) || b(259);
          const v = c.getNode();
          v.isEmpty() ? (function(E, k) {
            const N = k.getTextFormat(), C = k.getTextStyle();
            hi(E, N, C);
          })(a, v) : hi(a, a.format, "");
        }
      } else {
        const f = c.key, d = a.focus.key, h = a.getNodes(), _ = h.length, m = a.isBackward(), p = m ? s : i, g = m ? i : s, y = m ? d : f, x = m ? f : d;
        let v = 2047, E = !1;
        for (let k = 0; k < _; k++) {
          const N = h[k], C = N.getTextContentSize();
          if (O(N) && C !== 0 && !(k === 0 && N.__key === y && p === C || k === _ - 1 && N.__key === x && g === 0) && (E = !0, v &= N.getFormat(), v === 0)) break;
        }
        a.format = E ? v : 0;
      }
    }
    L(t, Vc, void 0);
  });
}
function hi(n, t, e) {
  n.format === t && n.style === e || (n.format = t, n.style = e, n.dirty = !0);
}
function mu(n, t) {
  hi(n, t.getFormat(), t.getStyle());
}
function yu(n) {
  if (!n.getTargetRanges) return null;
  const t = n.getTargetRanges();
  return t.length === 0 ? null : t[0];
}
function zl(n) {
  const { lastKeyCode: t } = j()._inputState;
  if (n == null || n.length <= 1 || t == null) return;
  const e = t.length === 1 ? t : t === "Enter" ? `
` : t === "Tab" ? "	" : null;
  if (!e) return;
  const r = M();
  if (!w(r) || !r.isCollapsed()) return;
  const i = r.anchor.getNode();
  if (!O(i)) return;
  const { offset: o } = r.anchor;
  if (i.getTextContentSize() === o) {
    const s = i.getNextSibling();
    if (e === `
`) {
      if (Pt(s)) s.selectEnd();
      else if (!s) {
        const l = dt(i, Kn), a = l && l.getNextSibling();
        S(a) && a.selectStart();
      }
    } else e === "	" ? Ar(s) && s.selectEnd() : O(s) && s.getTextContent()[0] === e && s.select(1, 1);
  } else i.getTextContent()[o] === e && i.select(o + 1, o + 1);
}
function Er(n) {
  n.isInsertTextAfterHandledSelectionCommand = !1, n.handledSelectionCommandTimeoutId !== null && (clearTimeout(n.handledSelectionCommandTimeoutId), n.handledSelectionCommandTimeoutId = null);
}
function Wl(n) {
  Er(n), n.isInsertTextAfterHandledSelectionCommand = !0, n.handledSelectionCommandTimeoutId = setTimeout(() => Er(n), 0);
}
function xu(n, t) {
  const e = df(n);
  if (F(e) && Wi(e, t)) return !0;
  const r = t.getRootElement();
  if (r === null) return !1;
  const i = Mr(r.ownerDocument);
  return i !== null && r.contains(i) && Wi(i, t);
}
function sh(n) {
  const t = n.inputType, e = yu(n), r = j(), i = r._inputState, o = M();
  if (t === "insertText" && n.data && i.isInsertTextAfterHandledSelectionCommand) {
    if (Er(i), n.preventDefault(), w(o) && !o.isCollapsed()) {
      const f = o.isBackward() ? o.anchor : o.focus;
      o.anchor.set(f.key, f.offset, f.type), o.focus.set(f.key, f.offset, f.type);
    }
    return !0;
  }
  if (t === "deleteContentBackward") {
    if (o === null) {
      const f = Xn();
      if (!w(f)) return !0;
      kt(f.clone());
    }
    if (w(o)) {
      const f = o.anchor.key === o.focus.key;
      if ((function(d, h) {
        return d.lastKeyCode === "MediaLast" && h < d.lastKeyDownTimeStamp + 30;
      })(i, n.timeStamp) && r.isComposing() && f) {
        if (St(null), i.lastKeyDownTimeStamp = 0, setTimeout(() => {
          Gt(r, () => {
            St(null);
          });
        }, 30), w(o)) {
          const d = o.anchor.getNode();
          d.markDirty(), O(d) || b(142), mu(o, d);
        }
      } else {
        if (St(null), Pe && e !== null && !e.collapsed && (o.applyDOMRange(e), !o.isCollapsed())) return n.preventDefault(), o.removeText(), !0;
        n.preventDefault();
        const d = o.anchor.getNode(), h = d.getTextContent(), _ = d.canInsertTextAfter(), m = o.anchor.offset === 0 && o.focus.offset === h.length;
        let p = Si && f && !m && _;
        if (p && o.isCollapsed() && (p = !W(jh(o.anchor, !0))), !p) {
          L(r, Ge, !0);
          const g = M();
          Si && w(g) && g.isCollapsed() && (i.postDeleteSelectionToRestore = g, setTimeout(() => i.postDeleteSelectionToRestore = null));
        }
      }
      return !0;
    }
  }
  if (!w(o)) return !0;
  const s = n.data;
  i.unprocessedBeforeInputData !== null && Ws(!1, r, i.unprocessedBeforeInputData), o.dirty && i.unprocessedBeforeInputData === null || !o.isCollapsed() || ut(o.anchor.getNode()) || e === null || o.applyDOMRange(e), i.unprocessedBeforeInputData = null;
  const l = o.anchor, a = o.focus, c = l.getNode(), u = a.getNode();
  if (t === "insertText" || t === "insertTranspose") {
    if (s === `
`) n.preventDefault(), L(r, An, !1);
    else if (s === Ve) n.preventDefault(), L(r, wr, void 0);
    else if (s == null && n.dataTransfer) {
      const f = n.dataTransfer.getData("text/plain");
      n.preventDefault(), o.insertRawText(f);
    } else s != null && _u(o, e, s, n.timeStamp, !0) ? (n.preventDefault(), L(r, Mn, s), zl(s)) : i.unprocessedBeforeInputData = s;
    return i.lastBeforeInputInsertTextTimeStamp = n.timeStamp, !0;
  }
  switch (n.preventDefault(), t) {
    case "insertFromYank":
    case "insertFromDrop":
    case "insertReplacementText":
      L(r, Mn, n), zl((n.dataTransfer ? n.dataTransfer.getData("text/plain") : null) ?? n.data);
      break;
    case "insertFromComposition": {
      const f = i.hadOrphanedCompositionEvents;
      i.hadOrphanedCompositionEvents = !1;
      const d = r._compositionKey;
      St(null), f || L(r, Mn, n), gi(d);
      break;
    }
    case "insertLineBreak":
      St(null), L(r, An, !1);
      break;
    case "insertParagraph":
      St(null), i.isInsertLineBreak && !Pe ? (i.isInsertLineBreak = !1, L(r, An, !1)) : L(r, wr, void 0);
      break;
    case "insertFromPaste":
    case "insertFromPasteAsQuotation":
      L(r, Es, n);
      break;
    case "deleteByComposition":
      (function(f, d) {
        return f !== d || S(f) || S(d) || !Xe(f) || !Xe(d);
      })(c, u) && L(r, di, n);
      break;
    case "deleteByDrag":
      Jn(es), L(r, di, n);
      break;
    case "deleteByCut":
      L(r, di, n);
      break;
    case "deleteContent":
      L(r, Ge, !1);
      break;
    case "deleteWordBackward":
      L(r, kr, !0);
      break;
    case "deleteWordForward":
      L(r, kr, !1);
      break;
    case "deleteHardLineBackward":
    case "deleteSoftLineBackward":
      L(r, Nr, !0);
      break;
    case "deleteContentForward":
    case "deleteHardLineForward":
    case "deleteSoftLineForward":
      L(r, Nr, !1);
      break;
    case "formatStrikeThrough":
      L(r, Ot, "strikethrough");
      break;
    case "formatBold":
      L(r, Ot, "bold");
      break;
    case "formatItalic":
      L(r, Ot, "italic");
      break;
    case "formatUnderline":
      L(r, Ot, "underline");
      break;
    case "historyUndo":
      L(r, Vi, void 0);
      break;
    case "historyRedo":
      L(r, Gi, void 0);
  }
  return !0;
}
function lh(n) {
  const t = j(), e = t._inputState, r = M(), i = n.data, o = yu(n);
  let s = !1;
  if (i != null && w(r)) {
    const l = Dt(It(t)), a = l !== null ? Qt(l, t._rootElement) : null, c = n.inputType === "insertCompositionText" && e.compositionPhase !== "ending-firefox" && !t.isComposing();
    c && (e.hadOrphanedCompositionEvents = !0);
    const u = r.anchor.getNode(), f = n.inputType === "insertCompositionText" && e.compositionPhase !== "ending-firefox" && t.isComposing() && O(u) && zt(u);
    if (!c && !f && _u(r, o, i, n.timeStamp, !1, a)) {
      if (s = !0, e.compositionPhase === "ending-firefox") {
        const g = ki(t, i);
        if (e.compositionPhase = "idle", g) return Jn(Ei), da(), !0;
      }
      const d = r.anchor.getNode();
      if (l === null || a === null) return !0;
      const h = r.isBackward(), _ = h ? r.anchor.offset : r.focus.offset, m = h ? r.focus.offset : r.anchor.offset;
      Wn && !r.isCollapsed() && O(d) && a.anchorNode !== null && d.getTextContent().slice(0, _) + i + d.getTextContent().slice(_ + m) === sf(a.anchorNode) || L(t, Mn, i);
      const p = i.length;
      Fe && p > 1 && n.inputType === "insertCompositionText" && !t.isComposing() && (r.anchor.offset -= p, r._cachedNodes = null, r._cachedIsBackward = null), Si && t.isComposing() && (e.lastKeyDownTimeStamp = 0, St(null));
    }
  }
  return s || (Ws(!1, t, i !== null ? i : void 0), e.compositionPhase === "ending-firefox" && (ki(t, i || void 0), Jn(Ei), e.compositionPhase = "idle")), da(), !0;
}
function ah(n) {
  const t = j(), e = t._inputState, r = M();
  if (w(r) && !t.isComposing()) {
    e.compositionPhase = "composing", e.hadOrphanedCompositionEvents = !1;
    const i = r.anchor, o = r.anchor.getNode();
    if (St(i.key), Jn(wu), n.timeStamp < e.lastKeyDownTimeStamp + 30 || i.type === "element" || !r.isCollapsed() || !Si && (o.getFormat() !== r.format || O(o) && o.getStyle() !== r.style) || O(o) && (zt(o) || i.offset === 0 && !o.canInsertTextBefore() || i.offset === o.getTextContentSize() && !o.canInsertTextAfter())) {
      L(t, Mn, ks);
      const s = M();
      w(s) && St(s.anchor.key);
    }
  }
  return !0;
}
function ch(n) {
  const t = j();
  return t._inputState.compositionPhase = "idle", ki(t, n.data), Jn(Ei), !0;
}
function gi(n) {
  if (n === null) return;
  const t = Z(n);
  if (!O(t) || t.getType() === "text" || zt(t) || !t.isAttached()) return;
  const e = M(), r = w(e) && e.anchor.key === n ? e.anchor.offset : null, i = mt(t.getTextContent());
  if (i.setFormat(t.getFormat()), i.setStyle(t.getStyle()), t.replace(i), r !== null) {
    const o = Math.min(r, i.getTextContentSize());
    i.select(o, o);
  }
}
function ki(n, t) {
  const e = n._compositionKey;
  if (St(null), e !== null && t != null) {
    if (t === "") {
      const i = Z(e), o = n.getElementByKey(e), s = o !== null && O(i) ? $e(i, o, n) : null;
      if (s !== null && s.nodeValue !== null && O(i)) {
        const l = Dt(It(n)), a = l && Qt(l, n._rootElement);
        let c = null, u = null;
        a !== null && a.anchorNode === s && (c = a.anchorOffset, u = a.focusOffset), Ks(i, s.nodeValue, c, u, !0);
      }
      return gi(e), !1;
    }
    if (t[t.length - 1] === `
`) {
      const i = M();
      if (w(i) || nt(i)) {
        if (w(i)) {
          const o = i.focus;
          i.anchor.set(o.key, o.offset, o.type);
        }
        return L(n, Ti, null), gi(e), !1;
      }
    }
    const r = Z(e);
    if (r !== null && O(r) && zt(r)) {
      r.markDirty();
      const i = M(), o = r.getTextContentSize(), s = w(i) && i.anchor.key === e ? i.anchor.offset : o;
      return r.select(s, s).insertText(t), !0;
    }
  }
  return Ws(!0, n, t), gi(e), !1;
}
function uh(n) {
  const t = j(), e = t._inputState;
  if (n.key == null) return !0;
  if (e.compositionPhase === "ending-safari") {
    const r = ga(n);
    if (r && Gt(t, () => {
      ki(t, e.compositionEndData);
    }), e.compositionPhase = "idle", e.compositionEndData = "", r) return !0;
  }
  if ((function(r) {
    return Y(r, "ArrowRight", { shiftKey: "any" });
  })(n)) L(t, eu, n);
  else if ((function(r) {
    return Y(r, "ArrowRight", { ...pe, shiftKey: "any" });
  })(n)) L(t, nu, n);
  else if ((function(r) {
    return Y(r, "ArrowLeft", { shiftKey: "any" });
  })(n)) L(t, ru, n);
  else if ((function(r) {
    return Y(r, "ArrowLeft", { ...pe, shiftKey: "any" });
  })(n)) L(t, iu, n);
  else if ((function(r) {
    return Y(r, "ArrowUp", { altKey: "any", shiftKey: "any" });
  })(n)) L(t, ou, n);
  else if ((function(r) {
    return Y(r, "ArrowDown", { altKey: "any", shiftKey: "any" });
  })(n)) L(t, su, n);
  else if ((function(r) {
    return Y(r, "Enter", { altKey: "any", ctrlKey: "any", metaKey: "any", shiftKey: !0 });
  })(n)) e.isInsertLineBreak = !0, L(t, Ti, n);
  else if ((function(r) {
    return r.key === " ";
  })(n)) L(t, lu, n);
  else if ((function(r) {
    return ne && Y(r, "o", { ctrlKey: !0 });
  })(n)) n.preventDefault(), e.isInsertLineBreak = !0, L(t, An, !0);
  else if ((function(r) {
    return Y(r, "Enter", { altKey: "any", ctrlKey: "any", metaKey: "any" });
  })(n)) e.isInsertLineBreak = !1, L(t, Ti, n);
  else if ((function(r) {
    return Y(r, "Backspace", { shiftKey: "any" }) || ne && Y(r, "h", { ctrlKey: !0 });
  })(n)) ga(n) ? L(t, Os, n) && Wl(e) : (n.preventDefault(), L(t, Ge, !0));
  else if ((function(r) {
    return r.key === "Escape";
  })(n)) L(t, au, n);
  else if ((function(r) {
    return Y(r, "Delete", {}) || ne && Y(r, "d", { ctrlKey: !0 });
  })(n)) (function(r) {
    return r.key === "Delete";
  })(n) ? L(t, cu, n) : (n.preventDefault(), L(t, Ge, !1));
  else if ((function(r) {
    return Y(r, "Backspace", ha);
  })(n)) n.preventDefault(), L(t, kr, !0);
  else if ((function(r) {
    return Y(r, "Delete", ha);
  })(n)) n.preventDefault(), L(t, kr, !1);
  else if ((function(r) {
    return ne && Y(r, "Backspace", { metaKey: !0 });
  })(n)) n.preventDefault(), L(t, Nr, !0);
  else if ((function(r) {
    return ne && (Y(r, "Delete", { metaKey: !0 }) || Y(r, "k", { ctrlKey: !0 }));
  })(n)) n.preventDefault(), L(t, Nr, !1);
  else if ((function(r) {
    return Y(r, "b", pe);
  })(n)) n.preventDefault(), L(t, Ot, "bold");
  else if ((function(r) {
    return Y(r, "u", pe);
  })(n)) n.preventDefault(), L(t, Ot, "underline");
  else if ((function(r) {
    return Y(r, "i", pe);
  })(n)) n.preventDefault(), L(t, Ot, "italic");
  else if ((function(r) {
    return Y(r, "Tab", { shiftKey: "any" });
  })(n)) L(t, uu, n);
  else if ((function(r) {
    return Y(r, "z", pe);
  })(n)) n.preventDefault(), L(t, Vi, void 0);
  else if ((function(r) {
    return ne ? Y(r, "z", { metaKey: !0, shiftKey: !0 }) : Y(r, "y", { ctrlKey: !0 }) || Y(r, "z", { ctrlKey: !0, shiftKey: !0 });
  })(n)) n.preventDefault(), L(t, Gi, void 0);
  else {
    const r = t._editorState._selection;
    (function(i) {
      return Y(i, "a", pe);
    })(n) ? (n.preventDefault(), L(t, pu, n) && Wl(e)) : r === null || w(r) || ((function(i) {
      return Y(i, "c", pe);
    })(n) ? (n.preventDefault(), L(t, Xi, n)) : (function(i) {
      return Y(i, "x", pe);
    })(n) && (n.preventDefault(), L(t, As, n)));
  }
  return (function(r) {
    return r.ctrlKey || r.shiftKey || r.altKey || r.metaKey;
  })(n) && t.dispatchCommand(nh, n), !0;
}
function Su(n) {
  let t = n.__lexicalEventHandles;
  return t === void 0 && (t = [], n.__lexicalEventHandles = t), t;
}
const Dn = /* @__PURE__ */ new Map();
function Kl(n) {
  const t = Gh(n.target);
  if (t === null) return;
  const e = Us(n.target);
  let r = null, i = null;
  const o = e !== null ? wi.get(e) : void 0;
  if (e !== null) {
    if (o !== void 0) {
      const f = o.editors;
      let d = o.hasShadowEditor;
      if (d === void 0) {
        d = !1;
        for (const h of f) if (h._rootElement !== null && be(h._rootElement.getRootNode())) {
          d = !0;
          break;
        }
        o.hasShadowEditor = d;
      }
      if (d) {
        let h = null, _ = null;
        for (const m of f) {
          const p = m._rootElement;
          if (p === null) continue;
          const g = Qt(t, p).anchorNode;
          if (g !== null && Ln(g) === m) {
            if (be(p.getRootNode())) {
              r = m, i = g;
              break;
            }
            h === null && (h = m, _ = g);
          }
        }
        r === null && h !== null && (r = h, i = _);
      } else {
        const h = t.anchorNode;
        h === null || F(h) && h.shadowRoot !== null || (r = Ln(h), r !== null && (i = h));
      }
    }
    if (r === null) {
      const f = Mr(e);
      r = f !== null ? Ln(f) : null;
    }
  }
  if (r === null) return;
  if (r._inputState.isSelectionChangeFromMouseDown) {
    if (o !== void 0) for (const f of o.editors) f._inputState.isSelectionChangeFromMouseDown = !1;
    Gt(r, () => {
      const f = Xn(), d = i ?? Qt(t, r._rootElement).anchorNode;
      (F(d) || Ht(d)) && kt(Ds(f, t, r, n));
    });
  }
  const s = zs(r), l = s[s.length - 1], a = l._key, c = Dn.get(a), u = c || l;
  u !== r && Rl(t, u, !1), Rl(t, r, !0), r !== l ? Dn.set(a, r) : c && Dn.delete(a);
}
function Bl(n) {
  n._lexicalHandled = !0;
}
function Ul(n) {
  return n._lexicalHandled === !0;
}
function fh(n) {
  const t = Zo.get(n);
  if (t === void 0) return;
  const e = wi.get(t);
  if (e === void 0) return;
  Zo.delete(n);
  const r = Kr(n);
  eo(r) ? ((function(o) {
    if (o._parentEditor !== null) {
      const s = zs(o), l = s[s.length - 1]._key;
      Dn.get(l) === o && Dn.delete(l);
    } else Dn.delete(o._key);
  })(r), e.editors.delete(r), e.hasShadowEditor = void 0, n.__lexicalEditor = null) : r && b(198);
  const i = Su(n);
  for (let o = 0; o < i.length; o++) i[o]();
  n.__lexicalEventHandles = [];
}
function Qo(n, t, e) {
  xt();
  const r = n.__key, i = n.getParent();
  if (i === null) return void (Tt(n) !== null && b(367, r, String(Tt(n))));
  const o = (function(l) {
    const a = M();
    if (!w(a) || !S(l)) return a;
    const { anchor: c, focus: u } = a, f = c.getNode(), d = u.getNode();
    return jn(f, l) && c.set(l.__key, 0, "element"), jn(d, l) && u.set(l.__key, 0, "element"), a;
  })(n);
  let s = !1;
  if (w(o) && t) {
    const l = o.anchor, a = o.focus;
    l.key === r && (Mi(l, n, i, n.getPreviousSibling(), n.getNextSibling()), s = !0), a.key === r && (Mi(a, n, i, n.getPreviousSibling(), n.getNextSibling()), s = !0);
  } else nt(o) && t && n.isSelected() && n.selectPrevious();
  if (w(o) && t && !s) {
    const l = n.getIndexWithinParent();
    ye(n), je(o, i, l, -1);
  } else ye(n);
  e || ot(i) || i.canBeEmpty() || !i.isEmpty() || Qo(i, t), t && o && ut(i) && i.isEmpty() && i.selectEnd();
}
const Cu = /* @__PURE__ */ Symbol.for("ephemeral");
function Ni(n) {
  return n[Cu] || !1;
}
const Hl = { configurable: !0, enumerable: !1, value: void 0, writable: !0 };
class Kt {
  __type;
  __key;
  __parent;
  __prev;
  __next;
  __state;
  [Cr];
  static getType() {
    const { ownNodeType: t } = js(this);
    return t === void 0 && b(64, this.name), t;
  }
  static clone(t) {
    b(65, this.name);
  }
  $config() {
    return {};
  }
  config(t, e) {
    const r = e.extends || mf(this.constructor);
    return Object.assign(e, { extends: r }), typeof t == "string" && Object.assign(e, { type: t }), { [t]: e };
  }
  afterCloneFrom(t) {
    this.__key === t.__key ? (this.__parent = t.__parent, this.__next = t.__next, this.__prev = t.__prev, this.__state = t.__state) : t.__state && (this.__state = t.__state.getWritable(this));
  }
  resetOnCopyNodeFrom(t) {
    this.__state && (this.__state = this.__state.getWritable(this).resetOnCopyNode());
  }
  static importDOM;
  constructor(t) {
    this.__type = this.constructor.getType(), this.__parent = null, this.__prev = null, this.__next = null, Object.defineProperty(this, "__state", Hl), Object.defineProperty(this, Cr, Hl), Qu(this, t);
  }
  getType() {
    return this.__type;
  }
  isInline() {
    b(137, this.constructor.name);
  }
  isAttached() {
    let t = this.__key;
    for (; t !== null; ) {
      if (t === "root") return !0;
      const e = Z(t);
      if (e === null) break;
      t = e.__parent !== null ? e.__parent : Tt(e);
    }
    return !1;
  }
  isSelected(t) {
    const e = t || M();
    if (e == null) return !1;
    const r = e.getNodes().some((i) => i.__key === this.__key);
    if (O(this)) return r;
    if (w(e) && e.anchor.type === "element" && e.focus.type === "element") {
      if (e.isCollapsed()) return !1;
      const i = this.getParent();
      if (W(this) && this.isInline() && i) {
        const o = e.isBackward() ? e.focus : e.anchor;
        if (i.is(o.getNode()) && o.offset === i.getChildrenSize() && this.is(i.getLastChild())) return !1;
      }
    }
    return r;
  }
  getKey() {
    return this.__key;
  }
  getIndexWithinParent() {
    const t = this.getParent();
    if (t === null) return -1;
    let e = t.getFirstChild(), r = 0;
    for (; e !== null; ) {
      if (this.is(e)) return r;
      r++, e = e.getNextSibling();
    }
    return -1;
  }
  getParent() {
    const t = this.getLatest().__parent;
    return t === null ? null : Z(t);
  }
  getParentOrThrow() {
    const t = this.getParent();
    return t === null && b(66, this.__key), t;
  }
  getTopLevelElement() {
    let t = this;
    for (; t !== null; ) {
      const e = t.getParent();
      if (ot(e) || Tt(t) !== null) return S(t) || t === this && W(t) || b(194), t;
      t = e;
    }
    return null;
  }
  getTopLevelElementOrThrow() {
    const t = this.getTopLevelElement();
    return t === null && b(67, this.__key), t;
  }
  getParents() {
    const t = [];
    let e = this.getParent();
    for (; e !== null; ) t.push(e), e = e.getParent();
    return t;
  }
  getParentKeys() {
    const t = [];
    let e = this.getParent();
    for (; e !== null; ) t.push(e.__key), e = e.getParent();
    return t;
  }
  getPreviousSibling() {
    const t = this.getLatest().__prev;
    return t === null ? null : Z(t);
  }
  getPreviousSiblings() {
    const t = [], e = this.getParent();
    if (e === null) return t;
    let r = e.getFirstChild();
    for (; r !== null && !r.is(this); ) t.push(r), r = r.getNextSibling();
    return t;
  }
  getNextSibling() {
    const t = this.getLatest().__next;
    return t === null ? null : Z(t);
  }
  getNextSiblings() {
    const t = [];
    let e = this.getNextSibling();
    for (; e !== null; ) t.push(e), e = e.getNextSibling();
    return t;
  }
  getCommonAncestor(t) {
    const e = S(this) ? this : this.getParent(), r = S(t) ? t : t.getParent(), i = e && r ? fs(e, r) : null;
    return i ? i.commonAncestor : null;
  }
  is(t) {
    return t != null && this.__key === t.__key;
  }
  isBefore(t) {
    const e = fs(this, t);
    return e !== null && (e.type === "descendant" || (e.type === "branch" ? vf(e) === -1 : (e.type !== "same" && e.type !== "ancestor" && b(279), !1)));
  }
  isParentOf(t) {
    return jn(t, this);
  }
  getNodesBetween(t) {
    const e = this.isBefore(t), r = [], i = /* @__PURE__ */ new Set();
    let o = this;
    for (; o !== null; ) {
      const s = o.__key;
      if (i.has(s) || (i.add(s), r.push(o)), o === t) break;
      const l = S(o) ? e ? o.getFirstChild() : o.getLastChild() : null;
      if (l !== null) {
        o = l;
        continue;
      }
      const a = e ? o.getNextSibling() : o.getPreviousSibling();
      if (a !== null) {
        o = a;
        continue;
      }
      const c = o.getParentOrThrow();
      if (i.has(c.__key) || r.push(c), c === t) break;
      let u = null, f = c;
      do {
        if (f === null && b(68), u = e ? f.getNextSibling() : f.getPreviousSibling(), f = f.getParent(), f === null) break;
        u !== null || i.has(f.__key) || r.push(f);
      } while (u === null);
      o = u;
    }
    return e || r.reverse(), r;
  }
  isDirty() {
    const t = j()._dirtyLeaves;
    return t !== null && t.has(this.__key);
  }
  getLatest() {
    if (Ni(this)) return this;
    const t = Z(this.__key);
    return t === null && b(113), t;
  }
  getWritable() {
    if (Ni(this)) return this;
    xt();
    const t = Ne(), e = j(), r = t._nodeMap, i = this.__key, o = this.getLatest(), s = e._cloneNotNeeded, l = M();
    if (l !== null && l.setCachedNodes(null), s.has(i)) return Ii(o), o;
    const a = gf(o);
    return s.add(i), Ii(a), r.set(i, a), a;
  }
  getTextContent() {
    return xf(this);
  }
  getTextContentSize() {
    return this.getTextContent().length;
  }
  createDOM(t, e) {
    b(70);
  }
  updateDOM(t, e, r) {
    b(71);
  }
  getDOMSlot(t) {
    return new Nc(t);
  }
  exportDOM(t) {
    return { element: this.createDOM(t._config, t) };
  }
  exportJSON() {
    const t = this.__state ? this.__state.toJSON() : void 0;
    return { type: this.__type, version: 1, ...t };
  }
  static importJSON(t) {
    b(18, this.name);
  }
  updateFromJSON(t) {
    return (function(e, r) {
      const i = e.getWritable(), o = r.$;
      let s = o;
      for (const l of zc(i).flatKeys) l in r && (s !== void 0 && s !== o || (s = { ...o }), s[l] = r[l]);
      return (i.__state || s) && Rc(e).updateFromJSON(s), i;
    })(this, t);
  }
  static transform() {
    return null;
  }
  remove(t) {
    Qo(this, !0, t);
  }
  replace(t, e) {
    xt();
    let r = M();
    r !== null && (r = r.clone()), ko(this, t);
    const i = this.getLatest(), o = this.__key, s = t.__key, l = t.getWritable(), a = this.getParentOrThrow().getWritable(), c = a.__size, u = l.getParent(), f = u !== null ? l.getIndexWithinParent() : -1;
    ye(l), u !== null && w(r) && je(r, u, f, -1);
    const d = i.getPreviousSibling(), h = i.getNextSibling(), _ = i.__prev, m = i.__next, p = i.__parent;
    Qo(i, !1, !0), d === null ? a.__first = s : d.getWritable().__next = s, l.__prev = _, h === null ? a.__last = s : h.getWritable().__prev = s, l.__next = m, l.__parent = p, a.__size = c;
    let g = 0;
    e && (S(this) && S(l) || b(139), g = l.getChildrenSize(), l.splice(g, 0, this.getChildren()));
    const y = Xt(this);
    if (y.length > 0) {
      Se(this) && Se(l) || b(368, this.__key, l.__key);
      for (const x of y) {
        const v = rn(this, x);
        v !== null && (gg(this, x), Sf(l, x, v));
      }
    }
    if (w(r)) {
      kt(r);
      const x = r.anchor, v = r.focus;
      x.key === o && (e && x.type === "element" ? x.set(l.__key, g + x.offset, "element") : Vl(x, l)), v.key === o && (e && v.type === "element" ? v.set(l.__key, g + v.offset, "element") : Vl(v, l));
    }
    return xe() === o && St(s), l;
  }
  insertAfter(t, e = !0) {
    xt(), ko(this, t);
    const r = this.getWritable(), i = t.getWritable();
    this.getParentOrThrow();
    const o = i.getParent(), s = M();
    let l = !1, a = !1;
    if (o !== null) {
      const h = t.getIndexWithinParent();
      if (w(s)) {
        const _ = o.__key, m = s.anchor, p = s.focus;
        l = m.type === "element" && m.key === _ && m.offset === h + 1, a = p.type === "element" && p.key === _ && p.offset === h + 1;
      }
      ye(i), e && w(s) && je(s, o, h, -1);
    } else ye(i);
    const c = this.getNextSibling(), u = this.getParentOrThrow().getWritable(), f = i.__key, d = r.__next;
    if (c === null ? u.__last = f : c.getWritable().__prev = f, u.__size++, r.__next = f, i.__next = d, i.__prev = r.__key, i.__parent = r.__parent, e && w(s)) {
      const h = this.getIndexWithinParent();
      je(s, u, h + 1);
      const _ = u.__key;
      l && s.anchor.set(_, h + 2, "element"), a && s.focus.set(_, h + 2, "element");
    }
    return t;
  }
  insertBefore(t, e = !0) {
    xt(), ko(this, t);
    const r = this.getWritable(), i = t.getWritable();
    this.getParentOrThrow();
    const o = i.__key, s = M(), l = i.getParent(), a = l !== null ? i.getIndexWithinParent() : -1;
    ye(i), l !== null && e && w(s) && je(s, l, a, -1);
    const c = this.getPreviousSibling(), u = this.getParentOrThrow().getWritable(), f = r.__prev, d = this.getIndexWithinParent();
    return c === null ? u.__first = o : c.getWritable().__next = o, u.__size++, r.__prev = o, i.__prev = f, i.__next = r.__key, i.__parent = r.__parent, e && w(s) && je(s, this.getParentOrThrow(), d), t;
  }
  isParentRequired() {
    return !1;
  }
  createParentElementNode() {
    return U();
  }
  selectStart() {
    return this.selectPrevious();
  }
  selectEnd() {
    return this.selectNext(0, 0);
  }
  selectPrevious(t, e) {
    xt();
    const r = ie(this);
    if (r !== null) return r.selectPrevious(t, e);
    const i = this.getPreviousSibling(), o = this.getParentOrThrow();
    if (i === null) return o.select(0, 0);
    if (S(i)) return i.select();
    if (!O(i)) {
      const s = i.getIndexWithinParent() + 1;
      return o.select(s, s);
    }
    return i.select(t, e);
  }
  selectNext(t, e) {
    xt();
    const r = ie(this);
    if (r !== null) return r.selectNext(t, e);
    const i = this.getNextSibling(), o = this.getParentOrThrow();
    if (i === null) return o.select();
    if (S(i)) return i.select(0, 0);
    if (!O(i)) {
      const s = i.getIndexWithinParent();
      return o.select(s, s);
    }
    return i.select(t, e);
  }
  markDirty() {
    this.getWritable();
  }
  reconcileObservedMutation(t, e) {
    this.markDirty();
  }
}
function vu(n) {
  return n instanceof Kt;
}
const ts = "historic", dh = "history-push", _r = "history-merge", bu = "paste", Tu = "cut", hh = "collaboration", gh = "skip-scroll-into-view", ph = "skip-dom-selection", es = "skip-selection-focus", wu = "composition-start", Ei = "composition-end", _h = "!important";
function Oi(n) {
  const t = {};
  if (!n) return t;
  let e = "", r = "", i = null, o = !1, s = !1, l = !1, a = 0;
  const c = n.length;
  let u = -1;
  for (let h = 0; h < c; h++) {
    const _ = n[h];
    if (o) _ === "*" && n[h + 1] === "/" && (o = !1, h++);
    else if (s) u === -1 && (u = h), s = !1;
    else if (i === null) if (_ !== "/" || n[h + 1] !== "*") if (_ !== '"' && _ !== "'") if (_ !== "(") if (_ !== ")") if (l || _ !== ":" || a !== 0) {
      if (_ === ";" && a === 0) {
        u !== -1 && (l ? r += n.slice(u, h) : e += n.slice(u, h), u = -1);
        const m = e.trim(), p = r.trim();
        m !== "" && p !== "" && (t[m] = p), e = "", r = "", l = !1;
        continue;
      }
      u === -1 && (u = h);
    } else u !== -1 && (e += n.slice(u, h), u = -1), l = !0;
    else u === -1 && (u = h), a = Math.max(0, a - 1);
    else u === -1 && (u = h), a++;
    else u === -1 && (u = h), i = _;
    else u !== -1 && (l ? r += n.slice(u, h) : e += n.slice(u, h), u = -1), o = !0, h++;
    else u === -1 && (u = h), _ === "\\" ? s = !0 : _ === i && (i = null);
  }
  u !== -1 && (l ? r += n.slice(u, c) : e += n.slice(u, c));
  const f = e.trim(), d = r.trim();
  return f !== "" && d !== "" && (t[f] = d), t;
}
function mh(n, t, e) {
  const r = e.trimEnd(), i = r.length - 10;
  i >= 0 && r.slice(i).toLowerCase() === _h ? n.setProperty(t, r.slice(0, i).trim(), "important") : n.setProperty(t, e, "");
}
function Or(n, t, e = "") {
  if (t === e) return;
  const r = Oi(e), i = Oi(t);
  for (const o in i) delete r[o], mh(n, o, i[o]);
  for (const o in r) n.removeProperty(o);
}
function yo(n, t) {
  return 16 & t ? "code" : t & ws ? "mark" : 32 & t ? "sub" : 64 & t ? "sup" : null;
}
function xo(n, t) {
  return 1 & t ? "strong" : 2 & t ? "em" : "span";
}
function ku(n, t, e, r, i) {
  const o = r.classList;
  let s = $n(i, "base");
  s !== void 0 && o.add(...s), s = $n(i, "underlineStrikethrough");
  let l = !1;
  const a = 8 & t && 4 & t;
  s !== void 0 && (8 & e && 4 & e ? (l = !0, a || o.add(...s)) : a && o.remove(...s));
  for (const c in Ie) {
    const u = Ie[c];
    if (s = $n(i, c), s !== void 0) if (e & u) {
      if (l && (c === "underline" || c === "strikethrough")) {
        t & u && o.remove(...s);
        continue;
      }
      ((t & u) === 0 || a && c === "underline" || c === "strikethrough") && o.add(...s);
    } else t & u && o.remove(...s);
  }
}
function Nu(n, t, e) {
  const r = e.isComposing(), i = n + (r ? Sr : ""), o = st(), s = ro(o).$getDOMSlot(e, t, o), l = s.getFirstChild();
  if (l === null || l.nodeType !== Node.TEXT_NODE) return void s.insertChild(V().createTextNode(i));
  const a = l, c = a.nodeValue;
  if (c !== i) if (r || Fe) {
    const [u, f, d] = (function(h, _) {
      const m = h.length, p = _.length;
      let g = 0, y = 0;
      for (; g < m && g < p && h[g] === _[g]; ) g++;
      for (; y + g < m && y + g < p && h[m - y - 1] === _[p - y - 1]; ) y++;
      return [g, m - g - y, _.slice(g, p - y)];
    })(c, i);
    f !== 0 && a.deleteData(u, f), a.insertData(u, d);
  } else a.nodeValue = i;
}
function Jl(n, t, e, r, i, o) {
  Nu(i, n, t);
  const s = o.theme.text;
  s !== void 0 && ku(0, 0, r, n, s);
}
function Qr(n, t) {
  const e = V().createElement(t);
  return e.appendChild(n), e;
}
function ns(n) {
  return n != null && n.__isInlineFormattable === !0;
}
class We extends Kt {
  __text;
  __format;
  __style;
  __mode;
  __detail;
  get __isInlineFormattable() {
    return !0;
  }
  static getType() {
    return "text";
  }
  static clone(t) {
    return new We(t.__text, t.__key);
  }
  afterCloneFrom(t) {
    super.afterCloneFrom(t), this.__text = t.__text, this.__format = t.__format, this.__style = t.__style, this.__mode = t.__mode, this.__detail = t.__detail;
  }
  constructor(t = "", e) {
    super(e), this.__text = t, this.__format = 0, this.__style = "", this.__mode = 0, this.__detail = 0;
  }
  getFormat() {
    return this.getLatest().__format;
  }
  getDetail() {
    return this.getLatest().__detail;
  }
  getMode() {
    const t = this.getLatest();
    return Dd[t.__mode];
  }
  getStyle() {
    return this.getLatest().__style;
  }
  isToken() {
    return this.getLatest().__mode === 1;
  }
  isComposing() {
    return this.__key === xe();
  }
  isSegmented() {
    return this.getLatest().__mode === 2;
  }
  isDirectionless() {
    return !!(1 & this.getLatest().__detail);
  }
  isUnmergeable() {
    return !!(2 & this.getLatest().__detail);
  }
  hasFormat(t) {
    const e = Ie[t];
    return (this.getFormat() & e) !== 0;
  }
  isSimpleText() {
    return this.__type === "text" && this.__mode === 0;
  }
  getTextContent() {
    return this.getLatest().__text;
  }
  getFormatFlags(t, e) {
    return en(this.getLatest().__format, t, e);
  }
  canHaveFormat() {
    return !0;
  }
  isInline() {
    return !0;
  }
  createDOM(t, e) {
    const r = this.__format, i = yo(0, r), o = xo(0, r), s = i === null ? o : i, l = V().createElement(s);
    let a = l;
    this.hasFormat("code") && l.setAttribute("spellcheck", "false"), i !== null && (a = V().createElement(o), l.appendChild(a)), Jl(a, this, 0, r, this.__text, t);
    const c = this.__style;
    return c !== "" && Or(l.style, c), l;
  }
  updateDOM(t, e, r) {
    const i = this.__text, o = t.__format, s = this.__format, l = yo(0, o), a = yo(0, s), c = xo(0, o), u = xo(0, s);
    if ((l === null ? c : l) !== (a === null ? u : a)) return !0;
    if (l === a && c !== u) {
      const m = e.firstChild;
      m == null && b(48);
      const p = V().createElement(u);
      return Jl(p, this, 0, s, i, r), e.replaceChild(p, m), !1;
    }
    let f = e;
    a !== null && l !== null && (f = e.firstChild, f == null && b(49)), Nu(i, f, this);
    const d = r.theme.text;
    d !== void 0 && o !== s && ku(0, o, s, f, d);
    const h = t.__style, _ = this.__style;
    return h !== _ && Or(e.style, _, h), !1;
  }
  static importDOM() {
    return { "#text": () => ({ conversion: Ch, priority: 0 }), b: () => ({ conversion: xh, priority: 0 }), code: () => ({ conversion: ge, priority: 0 }), em: () => ({ conversion: ge, priority: 0 }), i: () => ({ conversion: ge, priority: 0 }), mark: () => ({ conversion: ge, priority: 0 }), s: () => ({ conversion: ge, priority: 0 }), span: () => ({ conversion: yh, priority: 0 }), strong: () => ({ conversion: ge, priority: 0 }), sub: () => ({ conversion: ge, priority: 0 }), sup: () => ({ conversion: ge, priority: 0 }), u: () => ({ conversion: ge, priority: 0 }) };
  }
  static importJSON(t) {
    return mt().updateFromJSON(t);
  }
  updateFromJSON(t) {
    return super.updateFromJSON(t).setTextContent(t.text).setFormat(t.format).setDetail(t.detail).setMode(t.mode).setStyle(t.style);
  }
  exportDOM(t) {
    let { element: e } = super.exportDOM(t);
    return F(e) || b(132), e.style.whiteSpace = "pre-wrap", this.hasFormat("lowercase") ? e.style.textTransform = "lowercase" : this.hasFormat("uppercase") ? e.style.textTransform = "uppercase" : this.hasFormat("capitalize") && (e.style.textTransform = "capitalize"), this.hasFormat("bold") && (e = Qr(e, "b")), this.hasFormat("italic") && (e = Qr(e, "i")), this.hasFormat("strikethrough") && (e = Qr(e, "s")), this.hasFormat("underline") && (e = Qr(e, "u")), { element: e };
  }
  exportJSON() {
    return { detail: this.getDetail(), format: this.getFormat(), mode: this.getMode(), style: this.getStyle(), text: this.getTextContent(), ...super.exportJSON() };
  }
  selectionTransform(t, e) {
  }
  setFormat(t) {
    const e = this.getWritable();
    return e.__format = typeof t == "string" ? Ie[t] : t, e;
  }
  setDetail(t) {
    const e = this.getWritable();
    return e.__detail = typeof t == "string" ? Od[t] : t, e;
  }
  setStyle(t) {
    const e = this.getWritable();
    return e.__style = t, e;
  }
  toggleFormat(t) {
    const e = en(this.getFormat(), t, null);
    return this.setFormat(e);
  }
  toggleDirectionless() {
    const t = this.getWritable();
    return t.__detail ^= 1, t;
  }
  toggleUnmergeable() {
    const t = this.getWritable();
    return t.__detail ^= 2, t;
  }
  setMode(t) {
    const e = Md[t];
    if (this.__mode === e) return this;
    const r = this.getWritable();
    return r.__mode = e, r;
  }
  setTextContent(t) {
    if (this.__text === t) return this;
    const e = this.getWritable();
    return e.__text = t, e;
  }
  select(t, e) {
    xt();
    let r = t, i = e;
    const o = M(), s = this.getTextContent(), l = this.__key;
    if (typeof s == "string") {
      const a = s.length;
      r === void 0 && (r = a), i === void 0 && (i = a);
    } else r = 0, i = 0;
    if (!w(o)) return Ru(l, r, l, i, "text", "text");
    {
      const a = xe();
      a !== o.anchor.key && a !== o.focus.key || St(l), o.setTextNodeRange(this, r, this, i);
    }
    return o;
  }
  selectStart() {
    return this.select(0, 0);
  }
  selectEnd() {
    const t = this.getTextContentSize();
    return this.select(t, t);
  }
  spliceText(t, e, r, i) {
    const o = this.getWritable(), s = o.__text, l = r.length;
    let a = t;
    a < 0 && (a = l + a, a < 0 && (a = 0));
    const c = M();
    if (i && w(c)) {
      const f = t + l;
      c.setTextNodeRange(o, f, o, f);
    }
    const u = s.slice(0, a) + r + s.slice(a + e);
    return o.__text = u, o;
  }
  canInsertTextBefore() {
    return !0;
  }
  canInsertTextAfter() {
    return !0;
  }
  splitText(...t) {
    xt();
    const e = this.getLatest(), r = e.getTextContent();
    if (r === "") return [];
    const i = e.__key, o = xe(), s = r.length;
    t.sort((C, T) => C - T), t.push(s);
    const l = [], a = t.length;
    for (let C = 0, T = 0; C < s && T <= a; T++) {
      const A = t[T];
      A > C && (l.push(r.slice(C, A)), C = A);
    }
    const c = l.length;
    if (c === 1) return [e];
    const u = l[0], f = e.getParent();
    let d;
    const h = e.getFormat(), _ = e.getStyle(), m = e.__detail;
    let p = !1, g = null, y = null;
    const x = M();
    if (w(x)) {
      const [C, T] = x.isBackward() ? [x.focus, x.anchor] : [x.anchor, x.focus];
      C.type === "text" && C.key === i && (g = C), T.type === "text" && T.key === i && (y = T);
    }
    e.isSegmented() ? (d = mt(u), d.__format = h, d.__style = _, d.__detail = m, d.__state = kl(e, d), p = !0) : d = e.setTextContent(u);
    const v = [d];
    for (let C = 1; C < c; C++) {
      const T = mt(l[C]);
      T.__format = h, T.__style = _, T.__detail = m, T.__state = kl(e, T);
      const A = T.__key;
      o === i && St(A), v.push(T);
    }
    const E = g ? g.offset : null, k = y ? y.offset : null;
    let N = 0;
    for (const C of v) {
      if (!g && !y) break;
      const T = N + C.getTextContentSize();
      if (g !== null && E !== null && E <= T && E >= N && (g.set(C.getKey(), E - N, "text"), E < T && (g = null)), y !== null && k !== null && k <= T && k >= N) {
        y.set(C.getKey(), k - N, "text");
        break;
      }
      N = T;
    }
    if (f !== null) {
      (function(A) {
        const D = A.getPreviousSibling(), I = A.getNextSibling();
        D !== null && Ii(D), I !== null && Ii(I);
      })(this);
      const C = f.getWritable(), T = this.getIndexWithinParent();
      p ? (C.splice(T, 0, v), this.remove()) : C.splice(T, 1, v), w(x) && je(x, f, T, c - 1);
    }
    return v;
  }
  mergeWithSibling(t) {
    const e = t === this.getPreviousSibling();
    e || t === this.getNextSibling() || b(50);
    const r = this.__key, i = t.__key, o = this.__text, s = o.length;
    xe() === i && St(r);
    const l = M();
    if (w(l)) {
      const f = l.anchor, d = l.focus;
      f !== null && f.key === i && ea(f, e, r, t, s), d !== null && d.key === i && ea(d, e, r, t, s);
    }
    const a = t.__text, c = e ? a + o : o + a;
    this.setTextContent(c);
    const u = this.getWritable();
    return t.remove(), u;
  }
  isTextEntity() {
    return !1;
  }
}
function yh(n) {
  return { forChild: Ms(n.style), node: null };
}
function xh(n) {
  const t = n, e = t.style.fontWeight === "normal";
  return { forChild: Ms(t.style, e ? void 0 : "bold"), node: null };
}
const jl = /* @__PURE__ */ new WeakMap();
function Sh(n) {
  if (!F(n)) return !1;
  if (n.nodeName === "PRE") return !0;
  const t = n.style.whiteSpace;
  return typeof t == "string" && t.startsWith("pre");
}
function Ch(n) {
  const t = n;
  n.parentElement === null && b(129);
  let e = t.textContent || "";
  if ((function(r) {
    let i, o = r.parentNode;
    const s = [r];
    for (; o !== null && (i = jl.get(o)) === void 0 && !Sh(o); ) s.push(o), o = o.parentNode;
    const l = i === void 0 ? o : i;
    for (let a = 0; a < s.length; a++) jl.set(s[a], l);
    return l;
  })(t) !== null) return { node: Ls(e) };
  if (e = e.replace(/\r/g, "").replace(/[ \t\n]+/g, " "), e === "") return { node: null };
  if (e[0] === " ") {
    let r = t, i = !0;
    for (; r !== null && (r = ql(r, !1)) !== null; ) {
      const o = r.textContent || "";
      if (o.length > 0) {
        /[ \t\n]$/.test(o) && (e = e.slice(1)), i = !1;
        break;
      }
    }
    i && (e = e.slice(1));
  }
  if (e[e.length - 1] === " ") {
    let r = t, i = !0;
    for (; r !== null && (r = ql(r, !0)) !== null; )
      if ((r.textContent || "").replace(/^( |\t|\r?\n)+/, "").length > 0) {
        i = !1;
        break;
      }
    i && (e = e.slice(0, e.length - 1));
  }
  return e === "" ? { node: null } : { node: mt(e) };
}
function ql(n, t) {
  let e = n;
  for (; ; ) {
    let r;
    for (; (r = t ? e.nextSibling : e.previousSibling) === null; ) {
      const o = e.parentElement;
      if (o === null) return null;
      e = o;
    }
    if (e = r, F(e)) {
      const o = e.style.display;
      if (o === "" && !zi(e) || o !== "" && !o.startsWith("inline")) return null;
    }
    let i = e;
    for (; (i = t ? e.firstChild : e.lastChild) !== null; ) e = i;
    if (Ht(e)) return e;
    if (e.nodeName === "BR") return null;
  }
}
const vh = { code: "code", em: "italic", i: "italic", mark: "highlight", s: "strikethrough", strong: "bold", sub: "subscript", sup: "superscript", u: "underline" };
function ge(n) {
  const t = vh[n.nodeName.toLowerCase()];
  return t === void 0 ? { node: null } : { forChild: Ms(n.style, t), node: null };
}
function mt(n = "") {
  return Mt(new We(n));
}
function O(n) {
  return n instanceof We;
}
function Ms(n, t) {
  const e = n.fontWeight, r = n.textDecoration.split(" "), i = e === "700" || e === "bold", o = r.includes("line-through"), s = n.fontStyle === "italic", l = r.includes("underline"), a = n.verticalAlign;
  return (c) => ((O(c) || ns(c)) && (i && !c.hasFormat("bold") && c.toggleFormat("bold"), o && !c.hasFormat("strikethrough") && c.toggleFormat("strikethrough"), s && !c.hasFormat("italic") && c.toggleFormat("italic"), l && !c.hasFormat("underline") && c.toggleFormat("underline"), a !== "sub" || c.hasFormat("subscript") || c.toggleFormat("subscript"), a !== "super" || c.hasFormat("superscript") || c.toggleFormat("superscript"), t && !c.hasFormat(t) && c.toggleFormat(t)), c);
}
class Gn extends We {
  static getType() {
    return "tab";
  }
  static clone(t) {
    return new Gn(t.__key);
  }
  constructor(t) {
    super("	", t), this.__detail = 2;
  }
  static importDOM() {
    return null;
  }
  createDOM(t) {
    const e = super.createDOM(t), r = $n(t.theme, "tab");
    return r !== void 0 && e.classList.add(...r), e;
  }
  static importJSON(t) {
    return Yi().updateFromJSON(t);
  }
  setTextContent(t) {
    return super.setTextContent("	");
  }
  spliceText(t, e, r, i) {
    return r === "" && e === 0 || r === "	" && e === 1 || b(286), this;
  }
  setDetail(t) {
    return t !== 2 && b(127), this;
  }
  setMode(t) {
    return t !== "normal" && b(128), this;
  }
  canInsertTextBefore() {
    return !1;
  }
  canInsertTextAfter() {
    return !1;
  }
}
function Yi() {
  return Mt(new Gn());
}
function Ar(n) {
  return n instanceof Gn;
}
class bh {
  key;
  offset;
  type;
  _selection;
  constructor(t, e, r) {
    this._selection = null, this.key = t, this.offset = e, this.type = r;
  }
  is(t) {
    return this.key === t.key && this.offset === t.offset && this.type === t.type;
  }
  isBefore(t) {
    return this.key === t.key ? this.offset < t.offset : Lr(Wt(Ft(this, "next")), Wt(Ft(t, "next"))) < 0;
  }
  getNode() {
    const t = Z(this.key);
    return t === null && b(20), t;
  }
  set(t, e, r, i) {
    const o = this._selection, s = this.key;
    i && this.key === t && this.offset === e && this.type === r || (this.key = t, this.offset = e, this.type = r, Bn() || (xe() === s && St(t), o !== null && (o.setCachedNodes(null), w(o) && (o._cachedIsBackward = null), o.dirty = !0)));
  }
}
function ue(n, t, e) {
  return new bh(n, t, e);
}
function So(n, t) {
  let e = t.__key, r = n.offset, i = "element";
  if (O(t)) {
    i = "text";
    const o = t.getTextContentSize();
    r > o && (r = o);
  } else if (!S(t)) {
    const o = t.getNextSibling();
    if (O(o)) e = o.__key, r = 0, i = "text";
    else {
      const s = t.getParent();
      s && (e = s.__key, r = t.getIndexWithinParent() + 1);
    }
  }
  n.set(e, r, i);
}
function Vl(n, t) {
  if (S(t)) {
    const e = t.getLastDescendant();
    S(e) || O(e) ? So(n, e) : So(n, t);
  } else So(n, t);
}
class Zi {
  _nodes;
  _cachedNodes;
  dirty;
  constructor(t) {
    this._cachedNodes = null, this._nodes = t, this.dirty = !1;
  }
  getCachedNodes() {
    return this._cachedNodes;
  }
  setCachedNodes(t) {
    this._cachedNodes = t;
  }
  is(t) {
    if (!nt(t)) return !1;
    const e = this._nodes, r = t._nodes;
    return e.size === r.size && Array.from(e).every((i) => r.has(i));
  }
  isCollapsed() {
    return !1;
  }
  isBackward() {
    return !1;
  }
  getStartEndPoints() {
    return null;
  }
  add(t) {
    this.dirty = !0, this._nodes.add(t), this._cachedNodes = null;
  }
  delete(t) {
    this.dirty = !0, this._nodes.delete(t), this._cachedNodes = null;
  }
  clear() {
    this.dirty = !0, this._nodes.clear(), this._cachedNodes = null;
  }
  has(t) {
    return this._nodes.has(t);
  }
  clone() {
    return new Zi(new Set(this._nodes));
  }
  extract() {
    return this.getNodes();
  }
  insertRawText(t) {
  }
  insertText() {
  }
  insertNodes(t) {
    const e = this.getNodes().filter((s) => Tt(s) === null), r = e.length;
    if (r === 0) return;
    const i = e[r - 1];
    let o;
    if (O(i)) o = i.select();
    else {
      const s = i.getIndexWithinParent() + 1;
      o = i.getParentOrThrow().select(s, s);
    }
    o.insertNodes(t);
    for (let s = 0; s < r; s++) e[s].remove();
  }
  getNodes() {
    const t = this._cachedNodes;
    if (t !== null) return t;
    const e = this._nodes, r = [];
    for (const i of e) {
      const o = Z(i);
      o !== null && r.push(o);
    }
    return Bn() || (this._cachedNodes = r), r;
  }
  getTextContent() {
    const t = this.getNodes();
    let e = "";
    for (let r = 0; r < t.length; r++) e += t[r].getTextContent();
    return e;
  }
  deleteNodes() {
    const t = this.getNodes().filter((e) => Tt(e) === null);
    if ((M() || Xn()) === this && t[0]) {
      const e = G(t[0], "next");
      $r(oe(e, e));
    }
    for (const e of t) e.remove();
    Eu();
  }
}
function Eu() {
  const n = pt();
  if (n.isEmpty()) {
    const t = U();
    n.append(t), t.select();
  }
}
function w(n) {
  return n instanceof un;
}
class un {
  format;
  style;
  anchor;
  focus;
  _cachedNodes;
  _cachedIsBackward;
  dirty;
  constructor(t, e, r, i) {
    this.anchor = t, this.focus = e, t._selection = this, e._selection = this, this._cachedNodes = null, this._cachedIsBackward = null, this.format = r, this.style = i, this.dirty = !1;
  }
  getCachedNodes() {
    return this._cachedNodes;
  }
  setCachedNodes(t) {
    this._cachedNodes = t;
  }
  is(t) {
    return !!w(t) && this.anchor.is(t.anchor) && this.focus.is(t.focus) && this.format === t.format && this.style === t.style;
  }
  isCollapsed() {
    return this.anchor.is(this.focus);
  }
  getNodes() {
    const t = this._cachedNodes;
    if (t !== null) return t;
    const e = (function(r) {
      const i = [], [o, s] = r.getTextSlices();
      o && i.push(o.caret.origin);
      const l = /* @__PURE__ */ new Set(), a = /* @__PURE__ */ new Set();
      for (const c of r) if (Zt(c)) {
        const { origin: u } = c;
        i.length === 0 ? l.add(u) : (a.add(u), i.push(u));
      } else {
        const { origin: u } = c;
        S(u) && a.has(u) || i.push(u);
      }
      if (s && i.push(s.caret.origin), Te(r.focus) && S(r.focus.origin) && r.focus.getNodeAtCaret() === null) for (let c = Ut(r.focus.origin, "previous"); Zt(c) && l.has(c.origin) && !c.origin.isEmpty() && c.origin.is(i[i.length - 1]); c = Xs(c)) l.delete(c.origin), i.pop();
      for (; i.length > 1; ) {
        const c = i[i.length - 1];
        if (!S(c) || a.has(c) || c.isEmpty() || l.has(c)) break;
        i.pop();
      }
      if (i.length === 0 && r.isCollapsed()) {
        const c = Wt(r.anchor), u = Wt(r.anchor.getFlipped()), f = (h) => Yt(h) ? h.origin : h.getNodeAtCaret(), d = f(c) || f(u) || (r.anchor.getNodeAtCaret() ? c.origin : u.origin);
        i.push(d);
      }
      return i;
    })(tl(ds(this), "next"));
    return Bn() || (this._cachedNodes = e), e;
  }
  setTextNodeRange(t, e, r, i) {
    return this.anchor.set(t.__key, e, "text"), this.focus.set(r.__key, i, "text"), this;
  }
  getTextContent() {
    const t = this.getNodes();
    if (t.length === 0) return "";
    const e = t[0], r = t[t.length - 1], i = this.anchor, o = this.focus, s = i.isBefore(o), [l, a] = rs(this);
    let c = "", u = !0;
    for (let f = 0; f < t.length; f++) {
      const d = t[f];
      if (S(d) && !d.isInline()) {
        u || (c += `
`);
        let h = "";
        for (const _ of Xt(d)) {
          const m = rn(d, _);
          m !== null && (h += m.getTextContent());
        }
        h !== "" ? (c += h, u = !1) : u = !d.isEmpty();
      } else if (u = !1, O(d)) {
        let h = d.getTextContent();
        d === e ? d === r ? i.type === "element" && o.type === "element" && o.offset !== i.offset || (h = l < a ? h.slice(l, a) : h.slice(a, l)) : h = s ? h.slice(l) : h.slice(a) : d === r && (h = s ? h.slice(0, a) : h.slice(0, l)), c += h;
      } else !W(d) && !Pt(d) || d === r && this.isCollapsed() || (c += d.getTextContent());
    }
    return c;
  }
  applyDOMRange(t) {
    const e = j(), r = e.getEditorState()._selection, i = Iu(t.startContainer, t.startOffset, t.endContainer, t.endOffset, e, r);
    if (i === null) return;
    const [o, s, l] = i;
    this.anchor.set(o.key, o.offset, o.type, !0), this.focus.set(s.key, s.offset, s.type, !0), l && (this.dirty = !0), wn(this);
  }
  clone() {
    const t = this.anchor, e = this.focus;
    return new un(ue(t.key, t.offset, t.type), ue(e.key, e.offset, e.type), this.format, this.style);
  }
  toggleFormat(t) {
    this.format = en(this.format, t, null), this.dirty = !0;
  }
  setFormat(t) {
    this.format = t, this.dirty = !0;
  }
  setStyle(t) {
    this.style = t, this.dirty = !0;
  }
  hasFormat(t) {
    const e = Ie[t];
    return (this.format & e) !== 0;
  }
  insertRawText(t) {
    this.insertNodes(Ls(t));
  }
  insertText(t) {
    const e = this.anchor, r = this.focus, i = this.format, o = this.style;
    let s = e, l = r;
    !this.isCollapsed() && r.isBefore(e) && (s = r, l = e), s.type === "element" && (function(p, g, y, x) {
      const v = p.getNode(), E = v.getChildAtIndex(p.offset), k = mt();
      if (k.setFormat(y), k.setStyle(x), to(E)) E.splice(0, 0, [k]);
      else if (E !== null) {
        const N = ot(v) ? U().append(k) : k;
        E.insertBefore(N);
      } else if (ot(v)) {
        const N = v.getLastChild();
        S(N) && !N.isInline() && N.isEmpty() ? N.append(k) : v.append(U().append(k));
      } else v.append(k);
      p.is(g) && g.set(k.__key, 0, "text"), p.set(k.__key, 0, "text");
    })(s, l, i, o), l.type === "element" && ln(l, Wt(Ft(l, "next")));
    const a = s.offset;
    let c = l.offset;
    const u = this.getNodes(), f = u.length;
    let d = u[0];
    O(d) || b(26);
    const h = d.getTextContent().length, _ = d.getParentOrThrow();
    let m = u[f - 1];
    if (f === 1 && l.type === "element" && (c = h, l.set(s.key, c, "text")), this.isCollapsed() && a === h && (zt(d) || !d.canInsertTextAfter() || !_.canInsertTextAfter() && d.getNextSibling() === null)) {
      const p = d.getNextSibling();
      let g;
      if (O(p) && p.canInsertTextBefore() && !zt(p) ? g = p : (g = mt(), g.setFormat(i), g.setStyle(o), _.canInsertTextAfter() ? d.insertAfter(g) : _.insertAfter(g)), g.select(0, 0), d = g, t !== "") return void this.insertText(t);
    } else if (this.isCollapsed() && a === 0 && (zt(d) || !d.canInsertTextBefore() || !_.canInsertTextBefore() && d.getPreviousSibling() === null)) {
      const p = d.getPreviousSibling();
      let g;
      if (!O(p) || zt(p) ? (g = mt(), g.setFormat(i), _.canInsertTextBefore() ? d.insertBefore(g) : _.insertBefore(g)) : g = p, g.select(), d = g, t !== "") return void this.insertText(t);
    } else if (d.isSegmented() && a !== h) if (xe() !== null) d = d.setMode("normal").setFormat(i).setStyle(o);
    else {
      const p = mt(d.getTextContent());
      p.setFormat(i), d.replace(p), d = p;
    }
    else if (!this.isCollapsed() && t !== "") {
      const p = m.getParent();
      if (!_.canInsertTextBefore() || !_.canInsertTextAfter() || S(p) && (!p.canInsertTextBefore() || !p.canInsertTextAfter())) return this.insertText(""), $u(this.anchor, this.focus), void this.insertText(t);
    }
    if (f === 1) {
      if (Xe(d)) {
        const x = mt(t);
        return x.select(), void d.replace(x);
      }
      const p = d.getFormat(), g = d.getStyle();
      if (a !== c || p === i && g === o) {
        if (Ar(d)) {
          const x = mt(t);
          return x.setFormat(i), x.setStyle(o), x.select(), void d.replace(x);
        }
      } else {
        if (d.getTextContent() !== "") {
          const x = mt(t);
          if (x.setFormat(i), x.setStyle(o), x.select(), a === 0) d.insertBefore(x, !1);
          else {
            const [v] = d.splitText(a);
            v.insertAfter(x, !1);
          }
          return void (x.isComposing() && this.anchor.type === "text" && (this.anchor.offset -= t.length, this._cachedNodes = null, this._cachedIsBackward = null));
        }
        d.setFormat(i), d.setStyle(o);
      }
      const y = c - a;
      d = d.spliceText(a, y, t, !0), d.getTextContent() === "" ? d.remove() : this.anchor.type === "text" && (this.format = p, this.style = g, d.isComposing() && (this.anchor.offset -= t.length, this._cachedNodes = null, this._cachedIsBackward = null));
    } else {
      const p = /* @__PURE__ */ new Set([...d.getParentKeys(), ...m.getParentKeys()]), g = S(d) ? d : d.getParentOrThrow();
      let y = S(m) ? m : m.getParentOrThrow(), x = m;
      if (!g.is(y) && y.isInline()) do
        x = y, y = y.getParentOrThrow();
      while (y.isInline());
      if (l.type === "text" && (c !== 0 || m.getTextContent() === "") || l.type === "element" && m.getIndexWithinParent() < c) if (O(m) && !Xe(m) && c !== m.getTextContentSize()) {
        if (m.isSegmented()) {
          const C = mt(m.getTextContent());
          m.replace(C), m = C;
        }
        ut(l.getNode()) || l.type !== "text" || (O(m) || b(395), m = m.spliceText(0, c, "")), p.add(m.__key);
      } else {
        const C = m.getParentOrThrow();
        C.canBeEmpty() || C.getChildrenSize() !== 1 ? m.remove() : C.remove();
      }
      else p.add(m.__key);
      const v = y.getChildren(), E = new Set(u), k = g.is(y), N = g.isInline() && d.getNextSibling() === null ? g : d;
      for (let C = v.length - 1; C >= 0; C--) {
        const T = v[C];
        if (T.is(d) || S(T) && T.isParentOf(d)) break;
        T.isAttached() && (!E.has(T) || T.is(x) ? k || N.insertAfter(T, !1) : T.remove());
      }
      if (!k) {
        let C = y, T = null;
        for (; C !== null; ) {
          const A = C.getChildren(), D = A.length;
          (D === 0 || A[D - 1].is(T)) && (p.delete(C.__key), T = C), C = C.getParent();
        }
      }
      if (Xe(d)) if (a === h) d.select();
      else {
        const C = mt(t);
        C.select(), d.replace(C);
      }
      else d = d.spliceText(a, h - a, t, !0), d.getTextContent() === "" ? d.remove() : this.anchor.type === "text" && (this.format = d.getFormat(), this.style = d.getStyle(), d.isComposing() && (this.anchor.offset -= t.length, this._cachedNodes = null, this._cachedIsBackward = null));
      for (let C = 1; C < f; C++) {
        const T = u[C], A = T.__key;
        p.has(A) || T.remove();
      }
    }
  }
  removeText() {
    const t = M() === this;
    yi(this, vg(ds(this))), t && M() !== this && kt(this);
  }
  formatText(t, e = null) {
    Au(this, t, e);
  }
  insertNodes(t) {
    if (t.length === 0) return;
    this.isCollapsed() || this.removeText();
    const e = this.anchor.getNode();
    if (this.anchor.type === "element" && S(e) && Tt(e) !== null) {
      let m = e.isShadowRoot() ? e.getFirstChild() ?? e.append(U()).getFirstChild() : e.getFirstChild();
      if (e.isShadowRoot() && m !== null && !S(m)) {
        const p = U();
        m.insertBefore(p), m = p;
      }
      if (m !== null) {
        m.selectStart();
        const p = M();
        return w(p) || b(369), p.insertNodes(t);
      }
    }
    if (this.anchor.type === "element" && ot(e)) {
      const m = vo(t), p = m.getLastDescendant();
      return e.splice(this.anchor.offset, 0, m.getChildren()), void (p !== null && p.selectEnd());
    }
    let r = (this.isBackward() ? this.focus : this.anchor).getNode(), i = dt(r, bt);
    const o = t[t.length - 1];
    if (S(i) && "__language" in i) {
      if ("__language" in t[0]) this.insertText(t[0].getTextContent());
      else {
        const m = lr(this);
        i.splice(m, 0, t), o.selectEnd();
      }
      return;
    }
    if (!t.some((m) => (S(m) || W(m)) && !m.isInline())) {
      S(i) || b(211, r.constructor.name, r.getType());
      const m = lr(this);
      return i.splice(m, 0, t), void o.selectEnd();
    }
    if (S(i) && Tt(i) !== null) {
      const m = lr(this), p = os(t);
      i.splice(m, 0, p);
      const g = p[p.length - 1];
      return void (g !== void 0 ? g.selectEnd() : i.select(m, m));
    }
    if (i === null) {
      const m = vo(t), p = m.getLastDescendant();
      let g = Ft(this.anchor, "next");
      for (const y of m.getChildren()) g = Tf(y, g);
      return void (p !== null && p.selectEnd());
    }
    if (S(i) && !i.isParentRequired() && !ot(i.getParentOrThrow())) {
      const m = lr(this), p = os(t);
      i.splice(m, 0, p);
      const g = p[p.length - 1];
      return void (g !== void 0 ? g.selectEnd() : i.select(m, m));
    }
    const s = vo(t), l = s.getLastDescendant(), a = s.getChildren(), c = !S(i) || !i.isEmpty() ? this.insertParagraph() : null;
    c && !i.isAttached() && (r = this.anchor.getNode(), i = dt(r, bt));
    const u = a[a.length - 1];
    let f = a[0];
    var d;
    S(d = f) && bt(d) && !d.isEmpty() && S(i) && (!i.isEmpty() || i.canMergeWhenEmpty()) && (S(i) || b(211, r.constructor.name, r.getType()), i.append(...f.getChildren()), f = a[1]), f && (i === null && b(212, r.constructor.name, r.getType()), (function(m, p) {
      const g = p.getParentOrThrow().getLastChild();
      let y = p;
      const x = [p];
      for (; y !== g; ) y.getNextSibling() || b(140), y = y.getNextSibling(), x.push(y);
      let v = m;
      for (const E of x) v = v.insertAfter(E);
    })(i, f));
    const h = dt(l, bt);
    c && S(h) && (c.canMergeWhenEmpty() || bt(u)) && (h.append(...c.getChildren()), c.remove()), S(i) && i.isEmpty() && i.remove(), l.selectEnd();
    const _ = S(i) ? i.getLastChild() : null;
    Pt(_) && h !== i && _.remove();
  }
  insertParagraph() {
    const t = this.anchor.getNode();
    if (this.anchor.type === "element" && ot(t)) {
      const l = U();
      return t.splice(this.anchor.offset, 0, [l]), l.select(), l;
    }
    const e = lr(this), r = dt(this.anchor.getNode(), bt);
    if (r !== null && Tt(r) !== null) return null;
    S(r) || b(213);
    const i = r.getChildAtIndex(e), o = i ? [i, ...i.getNextSiblings()] : [], s = r.insertNewAfter(this, !1);
    return s ? (s.append(...o), s.selectStart(), s) : null;
  }
  insertLineBreak(t) {
    const e = Ke();
    if (this.insertNodes([e]), t) {
      const r = e.getParentOrThrow(), i = e.getIndexWithinParent();
      r.select(i, i);
    }
  }
  extract() {
    const t = [...this.getNodes()], e = t.length;
    let r = t[0], i = t[e - 1];
    const [o, s] = rs(this), l = this.isBackward(), [a, c] = l ? [this.focus, this.anchor] : [this.anchor, this.focus], [u, f] = l ? [s, o] : [o, s];
    if (e === 0) return [];
    if (e === 1) {
      if (O(r) && !this.isCollapsed()) {
        const d = r.splitText(u, f), h = u === 0 ? d[0] : d[1];
        return h ? (a.set(h.getKey(), 0, "text"), c.set(h.getKey(), h.getTextContentSize(), "text"), [h]) : [];
      }
      return [r];
    }
    if (O(r) && (u === r.getTextContentSize() ? t.shift() : u !== 0 && ([, r] = r.splitText(u), t[0] = r, a.set(r.getKey(), 0, "text"))), O(i)) {
      const d = i.getTextContent().length;
      f === 0 ? t.pop() : f !== d && ([i] = i.splitText(f), t[t.length - 1] = i, c.set(i.getKey(), i.getTextContentSize(), "text"));
    }
    return t;
  }
  modify(t, e, r) {
    if (Li(this, t, e, r)) return;
    const i = t === "move", o = j(), s = Dt(It(o));
    if (!s) return;
    const l = o._blockCursorElement, a = o._rootElement, c = this.focus.getNode();
    a === null || l === null || !S(c) || c.isInline() || c.canBeEmpty() || Ri(l, o, a);
    const u = Hn(o, this.focus.key);
    let f = u;
    if (this.focus.type === "text" && (f = O(c) ? $e(c, u, o) : null), this.dirty) {
      const d = Hn(o, this.anchor.key);
      let h = d;
      if (this.anchor.type === "text") {
        const _ = this.anchor.getNode();
        h = O(_) ? $e(_, d, o) : null;
      }
      h && f && Di(s, h, this.anchor.offset, f, this.focus.offset);
    }
    if (r === "character" && O(c) && c.isUnmergeable() && (e ? this.focus.offset === 0 : this.focus.offset === c.getTextContentSize())) {
      const d = G(c, e ? "previous" : "next").getNodeAtCaret();
      if (O(d)) {
        if (!i) {
          const h = d.getTextContentSize();
          return e ? this.focus.set(d.__key, h - 1, "text") : this.focus.set(d.__key, 1, "text"), void (this.dirty = !0);
        }
        {
          const h = o.getElementByKey(d.getKey()), _ = h ? $e(d, h, o) : null;
          if (_) {
            const m = e ? _.length : 0;
            Di(s, _, m, _, m);
          }
        }
      }
    }
    if (Du(s, t, e ? "backward" : "forward", r), s.rangeCount > 0) {
      const d = no(s, o._rootElement), h = d || s.getRangeAt(0), _ = this.anchor.getNode(), m = ut(_) ? _ : af(_);
      this.applyDOMRange(h), this.dirty = !0, !i && (Lu(this, e, m), (d ? s.direction !== "backward" : s.anchorNode === h.startContainer && s.anchorOffset === h.startOffset) || Mu(this));
    }
    r === "lineboundary" && Li(this, t, e, r, "decorators");
  }
  forwardDeletion(t, e, r) {
    if (!r && (t.type === "element" && S(e) && t.offset === e.getChildrenSize() || t.type === "text" && t.offset === e.getTextContentSize())) {
      const i = e.getParent(), o = e.getNextSibling() || (i === null ? null : i.getNextSibling());
      if (S(o) && o.isShadowRoot()) return !0;
    }
    return !1;
  }
  deleteCharacter(t) {
    const e = this.isCollapsed();
    if (this.isCollapsed()) {
      const r = this.anchor;
      let i = r.getNode();
      if (this.forwardDeletion(r, i, t)) return;
      const o = so(Ft(r, t ? "previous" : "next"));
      if (o.getTextSlices().every((l) => l === null || l.distance === 0)) {
        let l = { type: "initial" };
        for (const a of o.iterNodeCarets("shadowRoot")) if (Zt(a)) {
          if (!a.origin.isInline()) {
            if (a.origin.isShadowRoot()) {
              if (l.type === "merge-block") break;
              if (S(o.anchor.origin) && o.anchor.origin.isEmpty()) {
                const c = Wt(a);
                yi(this, oe(c, c)), o.anchor.origin.remove();
              }
              return;
            }
            l.type !== "merge-next-block" && l.type !== "merge-block" || (l = { block: l.block, caret: a, type: "merge-block" });
          }
        } else {
          if (l.type === "merge-block") break;
          if (Te(a)) {
            if (S(a.origin)) {
              if (a.origin.isInline()) {
                if (!a.origin.isParentOf(o.anchor.origin)) break;
              } else l = { block: a.origin, type: "merge-next-block" };
              continue;
            }
            if (W(a.origin)) {
              if (!a.origin.isIsolated()) if (Xt(a.origin).length > 0) {
                if (S(o.anchor.origin) && o.anchor.origin.isEmpty()) {
                  o.anchor.origin.remove();
                  const c = Ai();
                  c.add(a.origin.getKey()), kt(c);
                }
              } else if (l.type === "merge-next-block" && (a.origin.isKeyboardSelectable() || !a.origin.isInline()) && S(o.anchor.origin) && o.anchor.origin.isEmpty()) {
                o.anchor.origin.remove();
                const c = Ai();
                c.add(a.origin.getKey()), kt(c);
              } else a.origin.remove();
              return;
            }
            break;
          }
        }
        if (l.type === "merge-block") {
          const { caret: a, block: c } = l;
          return Xt(c).length > 0 ? void 0 : a.origin.isEmpty() && !c.isEmpty() && a.origin.getParent() === c.getParent() ? void a.origin.remove(!0) : (yi(this, oe(!a.origin.isEmpty() && c.isEmpty() ? gn(G(c, a.direction)) : o.anchor, a)), this.removeText());
        }
        for (let a = r.getNode(); a !== null; ) {
          if (Tt(a) !== null) return;
          if (S(a) && a.isShadowRoot()) break;
          a = a.getParent();
        }
      }
      const s = this.focus;
      if (Co(this, t, "character"), this.isCollapsed()) {
        if (t && r.offset === 0 && Xl(this, r.getNode())) return;
      } else {
        const l = s.type === "text" ? s.getNode() : null;
        if (i = r.type === "text" ? r.getNode() : null, l !== null && l.isSegmented()) {
          const a = s.offset, c = l.getTextContentSize();
          if (l.is(i) || t && a !== c || !t && a !== 0) return void Yl(l, t, a);
        } else if (i !== null && i.isSegmented()) {
          const a = r.offset, c = i.getTextContentSize();
          if (i.is(l) || t && a !== 0 || !t && a !== c) return void Yl(i, t, a);
        }
        (function(a, c) {
          const u = a.anchor, f = a.focus, d = u.getNode(), h = f.getNode();
          if (d === h && u.type === "text" && f.type === "text") {
            const _ = u.offset, m = f.offset, p = _ < m, g = p ? _ : m, y = p ? m : _, x = y - 1;
            g !== x && (function(v) {
              return !(rf(v) || wh(v));
            })(d.getTextContent().slice(g, y)) && (c ? f.set(f.key, x, f.type) : u.set(u.key, x, u.type));
          }
        })(this, t);
      }
    }
    if (this.removeText(), t && !e && this.isCollapsed() && this.anchor.type === "element" && this.anchor.offset === 0) {
      const r = this.anchor.getNode();
      r.isEmpty() && ut(r.getParent()) && r.getPreviousSibling() === null && Xl(this, r), Eu();
    }
  }
  deleteLine(t) {
    const e = is(this.anchor);
    if (e !== null && W(ie(e))) return this.isCollapsed() || this.focus.set(this.anchor.key, this.anchor.offset, this.anchor.type), void this.deleteCharacter(t);
    this.isCollapsed() && Co(this, t, "lineboundary"), this.isCollapsed() ? this.deleteCharacter(t) : dt(this.anchor.getNode(), bt) !== dt(this.focus.getNode(), bt) ? (this.focus.set(this.anchor.key, this.anchor.offset, this.anchor.type), this.deleteCharacter(t)) : this.removeText();
  }
  deleteWord(t) {
    if (this.isCollapsed()) {
      const e = this.anchor, r = e.getNode();
      if (this.forwardDeletion(e, r, t)) return;
      Co(this, t, "word");
    }
    this.isCollapsed() ? this.deleteCharacter(t) : this.removeText();
  }
  isBackward() {
    const t = this._cachedIsBackward;
    if (t !== null) return t;
    const e = this.focus.isBefore(this.anchor);
    return Bn() || (this._cachedIsBackward = e), e;
  }
  getStartEndPoints() {
    return [this.anchor, this.focus];
  }
}
function nt(n) {
  return n instanceof Zi;
}
function Ou(n, t) {
  if (nt(n)) {
    for (const g of n.getNodes()) ns(g) && g.setFormat(t(g.getFormat()));
    return;
  }
  if (n.isCollapsed()) return n.setFormat(t(n.format)), void St(null);
  const e = [];
  for (const g of n.getNodes()) O(g) ? e.push(g) : S(g) ? g.setTextFormat(t(g.getTextFormat())) : ns(g) && g.setFormat(t(g.getFormat()));
  const r = e.length;
  if (r === 0) return n.setFormat(t(n.format)), void St(null);
  const i = n.anchor, o = n.focus, s = n.isBackward(), l = s ? o : i, a = s ? i : o;
  let c = 0, u = e[0], f = l.type === "element" ? 0 : l.offset;
  if (l.type === "text" && f === u.getTextContentSize() && (c = 1, u = e[1], f = 0), u == null) return;
  const d = r - 1;
  let h = e[d];
  const _ = a.type === "text" ? a.offset : h.getTextContentSize();
  if (u.is(h)) {
    if (f === _) return;
    const g = t(u.getFormat());
    if (zt(u) || f === 0 && _ === u.getTextContentSize()) u.setFormat(g);
    else {
      const y = u.splitText(f, _), x = f === 0 ? y[0] : y[1];
      x.setFormat(g), l.type === "text" && l.set(x.__key, 0, "text"), a.type === "text" && a.set(x.__key, _ - f, "text");
    }
    return void (n.format = g);
  }
  f === 0 || zt(u) || ([, u] = u.splitText(f), f = 0);
  const m = t(u.getFormat());
  u.setFormat(m);
  const p = t(h.getFormat());
  _ > 0 && (_ === h.getTextContentSize() || zt(h) || ([h] = h.splitText(_)), h.setFormat(p));
  for (let g = c + 1; g < d; g++) {
    const y = e[g];
    y.setFormat(t(y.getFormat()));
  }
  l.type === "text" && l.set(u.__key, f, "text"), a.type === "text" && a.set(h.__key, _, "text"), n.format = m | p;
}
function Th(n, t) {
  const e = [];
  for (const [r, i] of Object.entries(t)) typeof i == "boolean" && e.push([r, i]);
  e.length !== 0 && Ou(n, (r) => {
    for (const [i, o] of e) r = en(r, i, o ? Ie[i] : 0);
    return r;
  });
}
function Au(n, t, e = null) {
  const r = e === null && w(n) ? en(n.format, t, null) : e;
  Ou(n, (i) => en(i, t, r));
}
function Gl(n) {
  const t = n.offset;
  if (n.type === "text") return t;
  const e = n.getNode();
  return t === e.getChildrenSize() ? e.getTextContent().length : 0;
}
function rs(n) {
  const t = n.getStartEndPoints();
  if (t === null) return [0, 0];
  const [e, r] = t;
  return e.type === "element" && r.type === "element" && e.key === r.key && e.offset === r.offset ? [0, 0] : [Gl(e), Gl(r)];
}
function Xl(n, t) {
  for (let e = t; e; e = e.getParent()) {
    if (S(e)) {
      if (e.collapseAtStart(n)) return !0;
      if (ot(e)) break;
    }
    if (e.getPreviousSibling()) break;
  }
  return !1;
}
function Mu(n) {
  const t = n.focus, e = n.anchor, r = e.key, i = e.offset, o = e.type;
  e.set(t.key, t.offset, t.type, !0), t.set(r, i, o, !0);
}
function Du(n, t, e, r) {
  n.modify(t, e, r);
}
function Lu(n, t, e) {
  const r = n.getNodes(), i = r.filter((l) => jn(l, e));
  if (i.length === 0 || i.length === r.length) return !1;
  const o = t ? i[0] : i[i.length - 1], s = S(o) ? o : o.getParentOrThrow();
  return t ? s.selectStart() : s.selectEnd(), !0;
}
function Co(n, t, e) {
  if (Li(n, "extend", t, e)) return;
  const r = j(), i = Dt(It(r));
  if (!i || typeof i.modify != "function") return;
  const o = r._blockCursorElement, s = r._rootElement, l = n.anchor, a = n.focus.getNode();
  s === null || o === null || !S(a) || a.isInline() || a.canBeEmpty() || Ri(o, r, s);
  const c = (T) => {
    const A = T.getNode(), D = r.getElementByKey(T.key);
    return D !== null && T.type === "text" && O(A) ? $e(A, D, r) : D;
  }, u = l.getNode(), f = c(l);
  if (f === null) return;
  const d = l.offset, h = n.isCollapsed(), _ = n.focus, m = h ? f : c(_);
  if (m === null) return;
  const p = _.offset;
  if (Di(i, m, p, m, p), Du(i, "move", t ? "backward" : "forward", e), i.rangeCount === 0) return;
  const g = no(i, s) || i.getRangeAt(0), y = g.startContainer, x = g.startOffset;
  if (h && e === "character" && l.type === "text" && O(u) && u.isUnmergeable() && d === (t ? 0 : u.getTextContentSize())) {
    const T = G(u, t ? "previous" : "next").getNodeAtCaret();
    if (O(T)) {
      const A = t ? T.getTextContentSize() - 1 : 1;
      return n.focus.set(T.__key, A, "text"), void (n.dirty = !0);
    }
  }
  if (h && e === "character" && l.type === "text") {
    const T = t ? 0 : u.getTextContentSize(), A = y === f ? x : d !== T ? T : -1;
    if (A >= 0) return void (A !== d && (n.focus.set(l.key, A, "text"), n.dirty = !0));
  }
  const [v, E, k, N] = t ? [y, x, f, d] : [f, d, y, x], C = ut(u) ? u : af(u);
  n.applyDOMRange({ collapsed: !1, endContainer: k, endOffset: N, startContainer: v, startOffset: E }), n.dirty = !0, !Lu(n, t, C) && t && Mu(n), e === "lineboundary" && Li(n, "extend", t, e, "decorators");
}
const wh = (() => {
  try {
    const n = new RegExp("\\p{Emoji}", "u"), t = n.test.bind(n);
    if (t("❤️") && t("#️⃣") && t("👍")) return t;
  } catch {
  }
  return () => !1;
})();
function Yl(n, t, e) {
  const r = n, i = r.getTextContent().split(/(?=\s)/g), o = i.length;
  let s = 0, l = 0;
  for (let c = 0; c < o; c++) {
    const u = c === o - 1;
    if (l = s, s += i[c].length, t && s === e || s > e || u) {
      i.splice(c, 1), u && (l = void 0);
      break;
    }
  }
  const a = i.join("").trim();
  a === "" ? r.remove() : (r.setTextContent(a), r.select(l, l));
}
function Zl(n, t, e, r) {
  let i, o = t, s = !1;
  if (F(n)) {
    let l = !1;
    const a = n.childNodes, c = a.length, u = r._blockCursorElement;
    o === c && c > 0 && (l = !0, o = c - 1), er(n, r) !== void 0 || Wi(n, r) || (s = !0);
    let f = a[o], d = !1;
    if (f === u) f = a[o + 1], d = !0;
    else if (u !== null) {
      const h = u.parentNode;
      n === h && t > Array.prototype.indexOf.call(h.children, u) && o--;
    }
    if (i = kn(f), O(i)) o = ae(i, l ? "next" : "previous");
    else {
      let h = kn(n);
      if (h === null) return null;
      if (S(h)) {
        const _ = r.getElementByKey(h.getKey());
        _ === null && b(214), [h, o] = te(h, _, r).resolveChildIndex(h, _, n, t), S(h) || b(215), l && o >= h.getChildrenSize() && (o = Math.max(0, h.getChildrenSize() - 1));
        let p = h.getChildAtIndex(o);
        if (S(p) && (function(g, y, x) {
          const v = g.getParent();
          return x === null || v === null || !v.canBeEmpty() || v !== x.getNode();
        })(p, 0, e)) {
          const g = l ? p.getLastDescendant() : p.getFirstDescendant();
          g === null ? h = p : (p = g, h = S(p) ? p : p.getParentOrThrow()), o = 0;
        }
        O(p) ? (i = p, h = null, o = ae(p, l ? "next" : "previous")) : p !== h && l && !d && (S(h) || b(216), o = Math.min(h.getChildrenSize(), o + 1));
      } else {
        const _ = ie(h), m = _ !== null ? _ : h, p = m.getIndexWithinParent(), g = r.getElementByKey(h.getKey());
        let y = "after";
        if (g !== null && kn(n) === h) {
          const x = te(h, g, r);
          x.element !== g ? y = x.resolveLeafPosition(g, n, t) : t === 0 && W(h) && (y = "before");
        }
        o = y === "before" ? p : p + 1, h = m.getParentOrThrow();
      }
      if (S(h)) return [ue(h.__key, o, "element"), s];
    }
  } else i = kn(n);
  return O(i) ? [ue(i.__key, ae(i, o, "clamp"), "text"), s] : null;
}
function Ql(n, t, e) {
  const r = n.offset, i = n.getNode();
  if (r === 0) {
    const o = i.getPreviousSibling(), s = i.getParent();
    if (t) {
      if ((e || !t) && o === null && S(s) && s.isInline()) {
        const l = s.getPreviousSibling();
        O(l) && n.set(l.__key, l.getTextContent().length, "text");
      }
    } else S(o) && !e && o.isInline() ? n.set(o.__key, o.getChildrenSize(), "element") : O(o) && !i.isUnmergeable() && n.set(o.__key, o.getTextContent().length, "text");
  } else if (r === i.getTextContent().length) {
    const o = i.getNextSibling(), s = i.getParent();
    if (t && S(o) && o.isInline()) n.set(o.__key, 0, "element");
    else if ((e || t) && o === null && S(s) && s.isInline() && !s.canInsertTextAfter() && s.getTextContentSize() > 1) {
      const l = s.getNextSibling();
      O(l) && n.set(l.__key, 0, "text");
    }
  }
}
function $u(n, t, e) {
  if (n.type === "text" && t.type === "text") {
    const r = n.isBefore(t), i = n.is(t);
    Ql(n, r, i), Ql(t, !r, i), i && t.set(n.key, n.offset, n.type);
  }
}
function is(n) {
  const t = Z(n.key);
  return t === null ? null : io(t);
}
function Fu(n, t, e) {
  const r = is(n), i = is(t);
  if (r === i || r !== null && i !== null && r.is(i)) return !1;
  const o = e(r, i);
  if (r !== null) return S(r) ? t.set(r.getKey(), o ? r.getChildrenSize() : 0, "element") : t.set(r.getKey(), o ? r.getTextContentSize() : 0, "text"), !0;
  const s = ie(i);
  if (s === null) return !1;
  const l = s.getParent();
  if (l === null) return !1;
  const a = s.getIndexWithinParent();
  return t.set(l.getKey(), o ? a + 1 : a, "element"), !0;
}
function Pu(n) {
  const t = Fu(n.anchor, n.focus, (e, r) => (function(i, o, s, l) {
    if (s !== null && l !== null) {
      const u = ie(s), f = ie(l);
      if (u !== null && u.is(f)) {
        for (const d of oo(u).values()) {
          if (d === s.getKey()) return !0;
          if (d === l.getKey()) return !1;
        }
        return !0;
      }
      return u === null || f === null || u.isBefore(f);
    }
    if (s !== null) {
      const u = ie(s), f = Z(o.key);
      return u === null || f === null || !(!u.is(f) && !u.isParentOf(f)) || u.isBefore(f);
    }
    const a = ie(l), c = Z(i.key);
    return a !== null && c !== null && !a.is(c) && !a.isParentOf(c) && c.isBefore(a);
  })(n.anchor, n.focus, e, r));
  return t && (n.dirty = !0), t;
}
function Iu(n, t, e, r, i, o) {
  if (n === null || e === null || !Wr(i, n, e)) return null;
  const s = Zl(n, t, w(o) ? o.anchor : null, i);
  if (s === null) return null;
  const l = Zl(e, r, w(o) ? o.focus : null, i);
  if (l === null) return null;
  const [a, c] = s, [u, f] = l;
  if (a.type === "element" && u.type === "element") {
    const h = kn(n), _ = kn(e);
    if (W(h) && W(_)) return null;
  }
  const d = i._slotsUsed && Fu(a, u, () => (n.compareDocumentPosition(e) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0);
  return $u(a, u), [a, u, c || f || d];
}
function Kn(n) {
  return S(n) && !n.isInline();
}
function Ru(n, t, e, r, i, o) {
  const s = Ne(), l = new un(ue(n, t, i), ue(e, r, o), 0, "");
  return l.dirty = !0, s._selection = l, l;
}
function zu() {
  const n = ue("root", 0, "element"), t = ue("root", 0, "element");
  return new un(n, t, 0, "");
}
function Ai() {
  return new Zi(/* @__PURE__ */ new Set());
}
function Ds(n, t, e, r) {
  const i = e._window;
  if (i === null) return null;
  const o = r || i.event, s = o ? o.type : void 0, l = s === "selectionchange", a = !Bo && (l || s === "beforeinput" || s === "compositionstart" || s === "compositionend" || s === "click" && o && o.detail === 3 || s === "drop" || s === void 0);
  let c, u, f, d;
  if (w(n) && !a) return n.clone();
  {
    if (t === null) return null;
    const v = Qt(t, e._rootElement);
    if (c = v.anchorNode, u = v.focusNode, f = v.anchorOffset, d = v.focusOffset, (l || s === void 0) && w(n) && !Wr(e, c, u)) return n.clone();
  }
  const h = Iu(c, f, u, d, e, n);
  if (h === null) return null;
  const [_, m, p] = h;
  let g = 0, y = "";
  if (w(n)) {
    const v = n.anchor;
    if (_.key === v.key) g = n.format, y = n.style;
    else {
      const E = _.getNode();
      O(E) ? (g = E.getFormat(), y = E.getStyle()) : S(E) && (g = E.getTextFormat(), y = E.getTextStyle());
    }
  }
  const x = new un(_, m, g, y);
  return p && (x.dirty = !0), x;
}
function M() {
  return Ne()._selection;
}
function Xn() {
  return j()._editorState._selection;
}
function je(n, t, e, r = 1) {
  const i = n.anchor, o = n.focus, s = i.getNode(), l = o.getNode();
  if (!t.is(s) && !t.is(l)) return;
  const a = t.__key;
  if (n.isCollapsed()) {
    const c = i.offset;
    if (e <= c && r > 0 || e < c && r < 0) {
      const u = Math.max(0, c + r);
      i.set(a, u, "element"), o.set(a, u, "element"), ta(n);
    }
  } else {
    const c = n.isBackward(), u = c ? o : i, f = u.getNode(), d = c ? i : o, h = d.getNode();
    if (t.is(f)) {
      const _ = u.offset;
      (e <= _ && r > 0 || e < _ && r < 0) && u.set(a, Math.max(0, _ + r), "element");
    }
    if (t.is(h)) {
      const _ = d.offset;
      (e <= _ && r > 0 || e < _ && r < 0) && d.set(a, Math.max(0, _ + r), "element");
    }
  }
  ta(n);
}
function ta(n) {
  const t = n.anchor, e = t.offset, r = n.focus, i = r.offset, o = t.getNode(), s = r.getNode();
  if (n.isCollapsed()) {
    if (!S(o)) return;
    const l = o.getChildrenSize(), a = e >= l, c = a ? o.getChildAtIndex(l - 1) : o.getChildAtIndex(e);
    if (O(c)) {
      let u = 0;
      a && (u = c.getTextContentSize()), t.set(c.__key, u, "text"), r.set(c.__key, u, "text");
    }
    return;
  }
  if (S(o)) {
    const l = o.getChildrenSize(), a = e >= l, c = a ? o.getChildAtIndex(l - 1) : o.getChildAtIndex(e);
    if (O(c)) {
      let u = 0;
      a && (u = c.getTextContentSize()), t.set(c.__key, u, "text");
    }
  }
  if (S(s)) {
    const l = s.getChildrenSize(), a = i >= l, c = a ? s.getChildAtIndex(l - 1) : s.getChildAtIndex(i);
    if (O(c)) {
      let u = 0;
      a && (u = c.getTextContentSize()), r.set(c.__key, u, "text");
    }
  }
}
function Mi(n, t, e, r, i) {
  let o = null, s = 0, l = null;
  r !== null ? (o = r.__key, O(r) ? (s = r.getTextContentSize(), l = "text") : S(r) && (s = r.getChildrenSize(), l = "element")) : i !== null && (o = i.__key, O(i) ? l = "text" : S(i) && (l = "element")), o !== null && l !== null ? n.set(o, s, l) : (s = t.getIndexWithinParent(), s === -1 && (s = e.getChildrenSize()), n.set(e.__key, s, "element"));
}
function ea(n, t, e, r, i) {
  n.type === "text" ? n.set(e, n.offset + (t ? 0 : i), "text") : n.offset > r.getIndexWithinParent() && n.set(n.key, n.offset - 1, "element");
}
function Di(n, t, e, r, i) {
  try {
    n.setBaseAndExtent(t, e, r, i);
  } catch {
  }
}
function na(n, t, e) {
  const r = Hn(n, t.getKey());
  if (S(t)) {
    const i = te(t, r, n);
    return [i.element, e + i.getFirstChildOffset()];
  }
  return [r, e];
}
function kh(n, t, e, r, i, o) {
  const s = o.getRootNode(), l = fn(s) || be(s) ? Mr(s) : null;
  if (i.has(hh) && l !== o || l !== null && Rs(l, l)) return;
  const a = Qt(r, o);
  let c;
  if (!w(t)) return void (n !== null && Wr(e, a.anchorNode, a.focusNode) && r.removeAllRanges());
  const u = t.anchor, f = t.focus, d = u.getNode(), h = f.getNode(), [_, m] = na(e, d, u.offset), [p, g] = na(e, h, f.offset), y = t.format, x = t.style, v = t.isCollapsed();
  let E = _, k = p, N = !1;
  if (u.type === "text" ? (E = O(d) ? $e(d, _, e) : null, N = d.getFormat() !== y || d.getStyle() !== x) : w(n) && n.anchor.type === "text" && (N = !0), f.type === "text" && (k = O(h) ? $e(h, p, e) : null), E !== null && k !== null) {
    if (v && (n === null || N || w(n) && (n.format !== y || n.style !== x)) && (function(C, T, A, D, I, B) {
      C._inputState.collapsedSelectionFormat = { format: T, key: I, offset: D, style: A, timeStamp: B };
    })(e, y, x, m, u.key, performance.now()), (r.type !== "Range" || !v) && a.anchorOffset === m && a.focusOffset === g && a.anchorNode === E && a.focusNode === k) {
      if (l === null || !o.contains(l)) {
        const C = l !== null ? Ln(l) : null;
        C !== null && C !== e || i.has(es) || o.focus({ preventScroll: !0 });
      }
      if (u.type !== "element") return;
    }
    if (Di(r, E, m, k, g), Fe && t.isCollapsed() && o !== null && !i.has(es)) {
      const C = as(o);
      if (C === null || !o.contains(C)) {
        const T = Mr(o.ownerDocument), A = T !== null ? Ln(T) : null;
        A !== null && A !== e || o.focus({ preventScroll: !0 });
      }
    }
    if (!i.has(gh) && t.isCollapsed() && o !== null && o === as(o)) {
      const C = w(t) && t.anchor.type === "element" ? E.childNodes[m] || null : (c === void 0 && (c = Yh(r, o)), c);
      if (C !== null) {
        let T;
        if (Ht(C)) {
          const A = C.ownerDocument.createRange();
          A.selectNode(C), T = A.getBoundingClientRect();
        } else T = C.getBoundingClientRect();
        (function(A, D, I) {
          const B = Us(I), P = Hs(B);
          if (B === null || P === null) return;
          const J = I.getBoundingClientRect();
          if (D.bottom < J.top) return;
          let { top: rt, bottom: lt } = D, yt = 0, _t = 0, at = I;
          for (; at !== null; ) {
            const ct = at === B.body;
            if (ct) {
              const Jt = P.visualViewport;
              if (Jt) {
                const gt = Jt.offsetTop;
                yt = gt, _t = gt + Jt.height;
              } else yt = 0, _t = It(A).innerHeight;
              const Ee = P.getComputedStyle(B.documentElement), ir = parseFloat(Ee.scrollPaddingTop), tt = parseFloat(Ee.scrollPaddingBottom);
              isFinite(ir) && (yt += ir), isFinite(tt) && (_t -= tt);
            } else {
              const Jt = at === I ? J : at.getBoundingClientRect();
              yt = Jt.top, _t = Jt.bottom;
            }
            let de = 0;
            if (rt < yt ? de = -(yt - rt) : lt > _t && (de = lt - _t), de !== 0) if (ct) P.scrollBy(0, de);
            else {
              const Jt = at.scrollTop;
              at.scrollTop += de;
              const Ee = at.scrollTop - Jt;
              rt -= Ee, lt -= Ee;
            }
            if (ct) break;
            at = dn(at);
          }
        })(e, T, o);
      }
    }
    (function(C) {
      C._inputState.isSelectionChangeFromDOMUpdate = !0;
    })(e);
  }
}
function Nh(n) {
  let t = M() || Xn();
  t === null && (t = pt().selectEnd()), t.insertNodes(n);
}
function Wu(n, t) {
  for (const e of n.split(/(\r?\n|\t)/)) e === `
` || e === `\r
` ? t.linebreak() : e === "	" ? t.tab() : e !== "" && t.text(e);
}
function Ls(n) {
  const t = [];
  return Wu(n, { linebreak: () => t.push(Ke()), tab: () => t.push(Yi()), text: (e) => t.push(mt(e)) }), t;
}
function os(n) {
  const t = [];
  for (const e of n) Pt(e) || (!S(e) && !W(e) || e.isInline() ? t.push(e) : S(e) && t.push(...os(e.getChildren())));
  return t;
}
function lr(n) {
  let t = n;
  n.isCollapsed() || t.removeText();
  const e = M();
  w(e) && (t = e), w(t) || b(161);
  const r = t.anchor;
  let i = r.getNode(), o = r.offset;
  for (; !bt(i) && Tt(i) === null; ) {
    const s = i;
    if ([i, o] = Eh(i, o), s.is(i)) break;
  }
  return o;
}
function Eh(n, t) {
  const e = n.getParent();
  if (!e) {
    const i = U();
    return pt().append(i), i.select(), [pt(), 0];
  }
  if (O(n)) {
    const i = n.splitText(t);
    if (i.length === 0) return [e, n.getIndexWithinParent()];
    const o = t === 0 ? 0 : 1;
    return [e, i[0].getIndexWithinParent() + o];
  }
  if (!S(n) || t === 0) return [e, n.getIndexWithinParent()];
  const r = n.getChildAtIndex(t);
  if (r) {
    const i = new un(ue(n.__key, t, "element"), ue(n.__key, t, "element"), 0, ""), o = n.insertNewAfter(i);
    o && o.append(r, ...r.getNextSiblings());
  }
  return [e, n.getIndexWithinParent() + 1];
}
function ra(n) {
  return Pt(n) || Js(n) || O(n) || n.isParentRequired();
}
function vo(n) {
  const t = U();
  let e = null;
  for (let r = 0; r < n.length; r++) {
    const i = n[r];
    if (ra(i)) {
      if (e === null) {
        e = i.createParentElementNode(), t.append(e);
        const o = n[r + 1];
        if (Pt(i) && (o === void 0 || !ra(o))) continue;
      }
      e.append(i);
    } else t.append(i), e = null;
  }
  return t;
}
function Li(n, t, e, r, i = "decorators-and-blocks") {
  if (t === "move" && r === "character" && !n.isCollapsed()) {
    const [u, f] = e === n.isBackward() ? [n.focus, n.anchor] : [n.anchor, n.focus];
    return f.set(u.key, u.offset, u.type), !0;
  }
  const o = Ft(n.focus, e ? "previous" : "next"), s = r === "lineboundary", l = t === "move";
  let a = o, c = i === "decorators-and-blocks";
  if (!Qs(a)) {
    for (const u of a) {
      c = !1;
      const { origin: f } = u;
      if (!W(f) || f.isIsolated() || (a = u, !s || !f.isInline())) break;
    }
    if (c) for (const u of so(o).iterNodeCarets(t === "extend" ? "shadowRoot" : "root")) {
      if (Zt(u)) u.origin.isInline() || (a = u);
      else {
        if (S(u.origin)) continue;
        W(u.origin) && !u.origin.isInline() && (a = u);
      }
      break;
    }
  }
  if (a === o) return !1;
  if (l && !s && W(a.origin) && a.origin.isKeyboardSelectable()) {
    const u = Ai();
    return u.add(a.origin.getKey()), kt(u), !0;
  }
  return a = Wt(a), l && ln(n.anchor, a), ln(n.focus, a), c || !s;
}
let Ct = null, ht = null, $t = !1, bo = !1, pi = !1;
const To = /* @__PURE__ */ new Set();
let _i = 0;
const ia = { characterData: !0, childList: !0, subtree: !0 };
function Bn() {
  return $t || Ct !== null && Ct._readOnly;
}
function xt() {
  $t && b(13);
}
function Ku() {
  _i > 99 && b(14);
}
function Ne() {
  return Ct === null && b(195, Bu()), Ct;
}
function Oh(n) {
  Ne() !== null && ht === null && (ht = n), ht !== n && Tc(378);
}
function j() {
  return ht === null && b(337, Bu()), ht;
}
function Ah() {
  j()._dirtyType = 2;
}
function Bu() {
  let n = 0;
  const t = /* @__PURE__ */ new Set(), e = Un.version;
  if (typeof window < "u") for (const i of uf(document)) {
    const o = Kr(i);
    if (eo(o)) n++;
    else if (o) {
      let s = String(o.constructor.version || "<0.17.1");
      s === e && (s += " (separately built, likely a bundler configuration issue)"), t.add(s);
    }
  }
  let r = ` Detected on the page: ${n} compatible editor(s) with version ${e}`;
  return t.size && (r += ` and incompatible editors with versions ${Array.from(t).join(", ")}`), r;
}
function Uu() {
  return ht;
}
function oa(n, t, e) {
  const r = t.__type, i = Yu(n, r);
  let o = e.get(r);
  o === void 0 && (o = Array.from(i.transforms), e.set(r, o));
  const s = o.length;
  for (let l = 0; l < s && (o[l](t), t.isAttached()); l++) ;
}
function sa(n, t) {
  return n !== void 0 && n.__key !== t && n.isAttached();
}
function Hu(n, t) {
  if (!t) return;
  const e = n._updateTags;
  let r = t;
  Array.isArray(t) || (r = [t]);
  for (const i of r) e.add(i);
}
function Mh(n) {
  return $i(n, j()._nodes);
}
function $i(n, t) {
  const e = n.type, r = t.get(e);
  r === void 0 && b(17, e);
  const i = r.klass;
  n.type !== i.getType() && b(18, i.name);
  const o = i.importJSON(n), s = n.children;
  if (S(o) && Array.isArray(s)) for (let a = 0; a < s.length; a++) {
    const c = $i(s[a], t);
    o.append(c);
  }
  const l = n.$slots;
  if (l) {
    Se(o) || b(379, i.name);
    for (const a in l)
      Sf(o, a, $i(l[a], t));
  }
  return o;
}
function la(n, t, e) {
  const r = Ct, i = $t, o = ht;
  Ct = t, $t = !0, ht = n;
  try {
    return e();
  } finally {
    Ct = r, $t = i, ht = o;
  }
}
function Ae(n, t) {
  const e = pi;
  pi = !0;
  try {
    (function(r, i) {
      const o = r._pendingEditorState, s = r._rootElement, l = r._headless || s === null;
      if (o === null) return void (!r._updating && r._deferred.length > 0 && aa(r, r._deferred));
      const a = r._editorState, c = a._selection, u = o._selection, f = r._dirtyType !== 0, d = Ct, h = $t, _ = ht, m = r._updating, p = r._observer;
      let g = null;
      if (r._pendingEditorState = null, r._editorState = o, !l && f && p !== null) {
        ht = r, Ct = o, $t = !1, r._updating = !0;
        try {
          const C = r._dirtyType, T = r._dirtyElements, A = r._dirtyLeaves;
          p.disconnect(), g = jd(a, o, r, C, T, A);
        } catch (C) {
          if (C instanceof Error && r._onError(C), bo) throw C;
          return Gu(r, null, s, o), $c(r), r._dirtyType = 2, bo = !0, Ae(r, a), void (bo = !1);
        } finally {
          p.observe(s, ia), r._updating = m, Ct = d, $t = h, ht = _;
        }
      }
      o._readOnly || (o._readOnly = !0);
      const y = r._dirtyLeaves, x = r._dirtyElements, v = r._normalizedNodes, E = r._updateTags;
      f && (r._dirtyType = 0, r._cloneNotNeeded.clear(), r._dirtyLeaves = /* @__PURE__ */ new Set(), r._dirtyElements = /* @__PURE__ */ new Map(), r._normalizedNodes = /* @__PURE__ */ new Set()), r._updateTags = /* @__PURE__ */ new Set(), (function(C, T) {
        const A = C._decorators;
        let D = C._pendingDecorators || A;
        const I = T._nodeMap;
        let B;
        for (B in D) I.has(B) || (D === A && (D = nf(C)), delete D[B]);
      })(r, o);
      const k = l ? null : Dt(It(r));
      if (r._editable && k !== null && (f || u === null || u.dirty || !u.is(c)) && s !== null && !E.has(ph)) {
        ht = r, Ct = o;
        try {
          if (p !== null && p.disconnect(), f || u === null || u.dirty) {
            const C = r._blockCursorElement;
            C !== null && Ri(C, r, s), kh(c, u, r, k, E, s);
          }
          (function(C, T, A) {
            let D = C._blockCursorElement;
            if (w(A) && A.isCollapsed() && A.anchor.type === "element" && T.contains(as(T))) {
              const I = A.anchor, B = I.getNode(), P = I.offset;
              let J = !1, rt = null;
              if (P === B.getChildrenSize())
                mi(B.getChildAtIndex(P - 1)) && (J = !0);
              else {
                const lt = B.getChildAtIndex(P);
                if (lt !== null && mi(lt)) {
                  const yt = lt.getPreviousSibling();
                  (yt === null || mi(yt)) && (J = !0, rt = C.getElementByKey(lt.__key));
                }
              }
              if (J) {
                const lt = te(B, C.getElementByKey(B.__key), C).element;
                return D === null && (C._blockCursorElement = D = (function(yt) {
                  const _t = yt.theme, at = V().createElement("div");
                  at.contentEditable = "false", at.setAttribute("data-lexical-cursor", "true");
                  let ct = _t.blockCursor;
                  if (ct !== void 0) {
                    if (typeof ct == "string") {
                      const de = Ce(ct);
                      ct = _t.blockCursor = de;
                    }
                    ct !== void 0 && at.classList.add(...ct);
                  }
                  return at;
                })(C._config)), T.style.caretColor = "transparent", void (rt === null ? lt.appendChild(D) : lt.insertBefore(D, rt));
              }
            }
            D !== null && Ri(D, C, T);
          })(r, s, u);
        } finally {
          p !== null && p.observe(s, ia), ht = _, Ct = d;
        }
      }
      g !== null && (function(C, T, A, D, I) {
        const B = Array.from(C._listeners.mutation), P = B.length;
        for (let J = 0; J < P; J++) {
          const [rt, lt] = B[J];
          for (const yt of lt) {
            const _t = T.get(yt);
            _t !== void 0 && rt(_t, { dirtyLeaves: D, prevEditorState: I, updateTags: A });
          }
        }
      })(r, g, E, y, a), w(u) || u === null || c !== null && c.is(u) || r.dispatchCommand(Vc, void 0);
      const N = r._pendingDecorators;
      N !== null && (r._decorators = N, r._pendingDecorators = null, mr("decorator", r, !0, N)), (function(C, T, A) {
        const D = fa(T), I = fa(A);
        D !== I && mr("textcontent", C, !0, I);
      })(r, i || a, o), mr("update", r, !0, { dirtyElements: x, dirtyLeaves: y, editorState: o, mutatedNodes: g, normalizedNodes: v, prevEditorState: i || a, tags: E }), !m && aa(r, r._deferred), (function(C) {
        const T = C._updates;
        if (T.length === 0) return void (C._cascadeCount = 0);
        if ((function(D) {
          To.has(D) || (To.add(D), setTimeout(() => {
            To.delete(D), D._cascadeCount = 0;
          }, 0));
        })(C), C._cascadeCount++ > 99) return C._updates = [], C._cascadeCount = 0, void C._onWarn(new Error(`One or more update listeners are endlessly enqueueing more updates. May have encountered infinite recursion caused by update listeners that trigger additional updates without a stop condition. Editor namespace: ${C._config.namespace}`));
        const A = T.shift();
        if (A) {
          const [D, I] = A;
          Qi(C, D, I);
        }
      })(r);
    })(n, t);
  } finally {
    pi = e;
  }
}
function mr(n, t, e, ...r) {
  const i = t._updating;
  t._updating = e;
  try {
    const o = t._listeners[n], s = Array.from(o);
    for (const [l, a] of s) {
      a && a();
      const c = l(...r);
      o.has(l) ? o.set(l, c) : c && c();
    }
  } finally {
    t._updating = i;
  }
}
function Ju(n, t, e, r) {
  const i = zs(n);
  let o;
  if (!pi) for (let s = 0; s < i.length; s++) i[s]._updating || (i[s]._cascadeCount = 0);
  for (let s = 4; s >= 0; s--) for (let l = 0; l < i.length; l++) {
    const a = i[l];
    if (l > 0 && a._updating) {
      o = a;
      break;
    }
    const c = a._commands.get(t);
    if (c !== void 0) {
      const u = c[s];
      if (u.size > 0) {
        let f = !1;
        if (Gt(a, () => {
          for (const d of u) if (d(e, r)) return void (f = !0);
        }), f) return f;
      }
    }
  }
  return o && o.update(() => {
    Ju(o, t, e, r);
  }), !1;
}
function aa(n, t) {
  if (n._deferred = [], t.length !== 0) {
    const e = n._updating;
    n._updating = !0;
    try {
      for (let r = 0; r < t.length; r++) t[r]();
    } finally {
      n._updating = e;
    }
  }
}
function ca(n, t) {
  const e = n._updates;
  let r = t || !1;
  for (; e.length !== 0; ) {
    const i = e.shift();
    if (i) {
      const [o, s] = i, l = n._pendingEditorState;
      let a;
      s !== void 0 && (a = s.onUpdate, s.skipTransforms && (r = !0), s.discrete && (l === null && b(191), l._flushSync = !0), a && n._deferred.push(a), Hu(n, s.tag)), l == null ? Qi(n, o, s) : o();
    }
  }
  return r;
}
function Qi(n, t, e) {
  const r = n._updateTags;
  let i, o = !1, s = !1;
  e !== void 0 && (i = e.onUpdate, Hu(n, e.tag), o = e.skipTransforms || !1, s = e.discrete || !1), i && n._deferred.push(i);
  const l = n._editorState;
  let a = n._pendingEditorState, c = !1;
  (a === null || a._readOnly) && (a = n._pendingEditorState = ju(a || l), c = !0), a._flushSync = s;
  const u = Ct, f = $t, d = ht, h = n._updating;
  Ct = a, $t = !1, n._updating = !0, ht = n;
  const _ = n._headless || n.getRootElement() === null;
  Ps(null);
  try {
    c && (_ ? l._selection !== null && (a._selection = l._selection.clone()) : a._selection = (function(y, x) {
      const v = y.getEditorState()._selection, E = Dt(It(y));
      return w(v) || v == null ? Ds(v, E, y, x) : v.clone();
    })(n, e && e.event || null));
    const p = n._compositionKey;
    t(), o = ca(n, o), (function(y, x) {
      const v = x.getEditorState()._selection, E = y._selection;
      if (w(E)) {
        const k = E.anchor, N = E.focus;
        let C;
        if (k.type === "text" && (C = k.getNode(), C.selectionTransform(v, E)), N.type === "text") {
          const T = N.getNode();
          C !== T && T.selectionTransform(v, E);
        }
      }
    })(a, n), n._dirtyType !== 0 && (o ? (function(y, x) {
      const v = x._dirtyLeaves, E = y._nodeMap;
      for (const k of v) {
        const N = E.get(k);
        O(N) && N.isAttached() && N.isSimpleText() && !N.isUnmergeable() && Ol(N);
      }
    })(a, n) : (function(y, x) {
      const v = x._dirtyLeaves, E = x._dirtyElements, k = y._nodeMap, N = xe(), C = /* @__PURE__ */ new Map();
      let T = v, A = T.size, D = E, I = D.size;
      for (; A > 0 || I > 0; ) {
        if (A > 0) {
          x._dirtyLeaves = /* @__PURE__ */ new Set();
          for (const B of T) {
            const P = k.get(B);
            O(P) && P.isAttached() && P.isSimpleText() && !P.isUnmergeable() && Ol(P), P !== void 0 && sa(P, N) && oa(x, P, C), v.add(B);
          }
          if (T = x._dirtyLeaves, A = T.size, A > 0) {
            _i++;
            continue;
          }
        }
        x._dirtyLeaves = /* @__PURE__ */ new Set(), x._dirtyElements = /* @__PURE__ */ new Map(), D.delete("root") && D.set("root", !0);
        for (const B of D) {
          const P = B[0], J = B[1];
          if (E.set(P, J), !J) continue;
          const rt = k.get(P);
          rt !== void 0 && sa(rt, N) && oa(x, rt, C);
        }
        T = x._dirtyLeaves, A = T.size, D = x._dirtyElements, I = D.size, _i++;
      }
      x._dirtyLeaves = v, x._dirtyElements = E;
    })(a, n), ca(n), (function(y, x, v, E) {
      const k = y._nodeMap, N = x._nodeMap, C = [];
      for (const [D] of E) {
        const I = N.get(D);
        I !== void 0 && (I.isAttached() || (S(I) && vi(I, D, k, N, C, E), k.has(D) || E.delete(D), C.push(D)));
      }
      for (const D of v) {
        const I = N.get(D);
        I === void 0 || I.isAttached() || (Se(I) && I.__slots !== null && vi(I, D, k, N, C, v), k.has(D) || v.delete(D), C.push(D));
      }
      for (const D of C) N.delete(D);
      const T = j(), A = T._compositionKey;
      A === null || N.has(A) || (T._compositionKey = null);
    })(l, a, n._dirtyLeaves, n._dirtyElements)), p !== n._compositionKey && (a._flushSync = !0);
    const g = a._selection;
    if (w(g)) {
      n._slotsUsed && Pu(g);
      const y = a._nodeMap, x = g.anchor.key, v = g.focus.key;
      y.get(x) !== void 0 && y.get(v) !== void 0 || b(19);
    } else nt(g) && g._nodes.size === 0 && (a._selection = null);
  } catch (p) {
    return p instanceof Error && n._onError(p), n._pendingEditorState = l, n._dirtyType = 2, n._cloneNotNeeded.clear(), n._dirtyLeaves = /* @__PURE__ */ new Set(), n._dirtyElements.clear(), void Ae(n);
  } finally {
    Ct = u, $t = f, ht = d, n._updating = h, _i = 0;
  }
  n._dirtyType !== 0 || n._deferred.length > 0 || (function(p, g) {
    const y = g.getEditorState()._selection, x = p._selection;
    if (x !== null) {
      if (x.dirty || !x.is(y)) return !0;
    } else if (y !== null) return !0;
    return !1;
  })(a, n) ? a._flushSync ? (a._flushSync = !1, Ae(n)) : c && Bh(() => {
    Ae(n);
  }) : (a._flushSync = !1, c && (r.clear(), n._deferred = [], n._pendingEditorState = null));
}
function Gt(n, t, e) {
  ht === n && e === void 0 ? t() : Qi(n, t, e);
}
function Dh(n) {
  if (ot(n)) {
    let t = null;
    for (const e of n.getChildren()) t = e.isInline() ? (t || e.replace(e.createParentElementNode())).append(e) : null;
  }
}
class Nt extends Kt {
  __first;
  __last;
  __size;
  __format;
  __style;
  __indent;
  __dir;
  __textFormat;
  __textStyle;
  __slotHost;
  __slots;
  $config() {
    return this.config(/* @__PURE__ */ Symbol.for("ElementNode"), { $transform: Dh, extends: Kt });
  }
  constructor(t) {
    super(t), this.__first = null, this.__last = null, this.__size = 0, this.__format = 0, this.__style = "", this.__indent = 0, this.__dir = null, this.__textFormat = 0, this.__textStyle = "", this.__slotHost = null, this.__slots = null;
  }
  afterCloneFrom(t) {
    super.afterCloneFrom(t), this.__key === t.__key && (this.__first = t.__first, this.__last = t.__last, this.__size = t.__size, this.__slotHost = t.__slotHost, this.__slotHost !== null && this.__parent !== null && b(384, this.__key, String(this.__slotHost), String(this.__parent)), this.__slots = t.__slots), this.__indent = t.__indent, this.__format = t.__format, this.__style = t.__style, this.__dir = t.__dir, this.__textFormat = t.__textFormat, this.__textStyle = t.__textStyle;
  }
  getFormat() {
    return this.getLatest().__format;
  }
  getFormatType() {
    const t = this.getFormat();
    return Ad[t] || "";
  }
  getStyle() {
    return this.getLatest().__style;
  }
  getIndent() {
    return this.getLatest().__indent;
  }
  getChildren() {
    const t = [];
    let e = this.getFirstChild();
    for (; e !== null; ) t.push(e), e = e.getNextSibling();
    return t;
  }
  getChildrenKeys() {
    const t = [];
    let e = this.getFirstChild();
    for (; e !== null; ) t.push(e.__key), e = e.getNextSibling();
    return t;
  }
  getChildrenSize() {
    return this.getLatest().__size;
  }
  isEmpty() {
    return this.getChildrenSize() === 0 && Xt(this).length === 0;
  }
  isDirty() {
    const t = j()._dirtyElements;
    return t !== null && t.has(this.__key);
  }
  isLastChild() {
    const t = this.getLatest(), e = this.getParentOrThrow().getLastChild();
    return e !== null && e.is(t);
  }
  getAllTextNodes() {
    const t = [];
    for (const r of Xt(this)) {
      const i = rn(this, r);
      S(i) && t.push(...i.getAllTextNodes());
    }
    let e = this.getFirstChild();
    for (; e !== null; ) {
      if (O(e) && t.push(e), S(e)) {
        const r = e.getAllTextNodes();
        t.push(...r);
      }
      e = e.getNextSibling();
    }
    return t;
  }
  getFirstDescendant() {
    let t = this.getFirstChild();
    for (; S(t); ) {
      const e = t.getFirstChild();
      if (e === null) break;
      t = e;
    }
    return t;
  }
  getLastDescendant() {
    let t = this.getLastChild();
    for (; S(t); ) {
      const e = t.getLastChild();
      if (e === null) break;
      t = e;
    }
    return t;
  }
  getDescendantByIndex(t) {
    const e = this.getChildren(), r = e.length;
    if (t >= r) {
      const o = e[r - 1];
      return S(o) && o.getLastDescendant() || o || null;
    }
    const i = e[t];
    return S(i) && i.getFirstDescendant() || i || null;
  }
  getFirstChild() {
    const t = this.getLatest().__first;
    return t === null ? null : Z(t);
  }
  getFirstChildOrThrow() {
    const t = this.getFirstChild();
    return t === null && b(45, this.__key), t;
  }
  getLastChild() {
    const t = this.getLatest().__last;
    return t === null ? null : Z(t);
  }
  getLastChildOrThrow() {
    const t = this.getLastChild();
    return t === null && b(96, this.__key), t;
  }
  getChildAtIndex(t) {
    const e = this.getChildrenSize();
    let r, i;
    if (t < e / 2) {
      for (r = this.getFirstChild(), i = 0; r !== null && i <= t; ) {
        if (i === t) return r;
        r = r.getNextSibling(), i++;
      }
      return null;
    }
    for (r = this.getLastChild(), i = e - 1; r !== null && i >= t; ) {
      if (i === t) return r;
      r = r.getPreviousSibling(), i--;
    }
    return null;
  }
  getTextContent() {
    let t = xf(this);
    const e = this.getChildren(), r = e.length;
    for (let i = 0; i < r; i++) {
      const o = e[i];
      t += o.getTextContent(), S(o) && i !== r - 1 && !o.isInline() && (t += Ve);
    }
    return t;
  }
  getTextContentSize() {
    let t = (function(i) {
      let o = 0;
      for (const s of Xt(i)) {
        const l = rn(i, s);
        l !== null && (o += l.getTextContentSize());
      }
      return o;
    })(this);
    const e = this.getChildren(), r = e.length;
    for (let i = 0; i < r; i++) {
      const o = e[i];
      t += o.getTextContentSize(), S(o) && i !== r - 1 && !o.isInline() && (t += 2);
    }
    return t;
  }
  getDirection() {
    return this.getLatest().__dir;
  }
  getTextFormat() {
    return this.getLatest().__textFormat;
  }
  hasFormat(t) {
    if (t !== "") {
      const e = Ci[t];
      return (this.getFormat() & e) !== 0;
    }
    return !1;
  }
  hasTextFormat(t) {
    const e = Ie[t];
    return (this.getTextFormat() & e) !== 0;
  }
  getFormatFlags(t, e) {
    return en(this.getLatest().__textFormat, t, e);
  }
  getTextStyle() {
    return this.getLatest().__textStyle;
  }
  select(t, e) {
    xt();
    const r = M();
    let i = t, o = e;
    const s = this.getChildrenSize();
    if (!this.canBeEmpty()) {
      if (t === 0 && e === 0) {
        const a = this.getFirstChild();
        if (O(a) || S(a)) return a.select(0, 0);
      } else if (!(t !== void 0 && t !== s || e !== void 0 && e !== s)) {
        const a = this.getLastChild();
        if (O(a) || S(a)) return a.select();
      }
    }
    i === void 0 && (i = s), o === void 0 && (o = s);
    const l = this.__key;
    return w(r) ? (r.anchor.set(l, i, "element"), r.focus.set(l, o, "element"), r.dirty = !0, r) : Ru(l, i, l, o, "element", "element");
  }
  selectStart() {
    const t = this.getFirstDescendant();
    return t ? t.selectStart() : this.select();
  }
  selectEnd() {
    const t = this.getLastDescendant();
    return t ? t.selectEnd() : this.select();
  }
  clear() {
    const t = this.getWritable();
    return this.getChildren().forEach((e) => e.remove()), t;
  }
  append(...t) {
    return this.splice(this.getChildrenSize(), 0, t);
  }
  setDirection(t) {
    const e = this.getWritable();
    return e.__dir = t, e;
  }
  setFormat(t) {
    return this.getWritable().__format = t !== "" && Ci[t] || 0, this;
  }
  setStyle(t) {
    return this.getWritable().__style = t || "", this;
  }
  setTextFormat(t) {
    const e = this.getWritable();
    return e.__textFormat = t, e;
  }
  setTextStyle(t) {
    const e = this.getWritable();
    return e.__textStyle = t, e;
  }
  setIndent(t) {
    return this.getWritable().__indent = t, this;
  }
  splice(t, e, r) {
    Ni(this) && b(324, this.__key, this.__type);
    const i = this.getChildrenSize(), o = this.getWritable();
    t + e <= i || b(226, String(t), String(e), String(i));
    for (const h of r) ;
    const s = o.__key, l = [], a = [], c = this.getChildAtIndex(t + e);
    let u = null, f = i - e + r.length;
    if (t !== 0) if (t === i) u = this.getLastChild();
    else {
      const h = this.getChildAtIndex(t);
      h !== null && (u = h.getPreviousSibling());
    }
    if (e > 0) {
      let h = u === null ? this.getFirstChild() : u.getNextSibling();
      for (let _ = 0; _ < e; _++) {
        h === null && b(100);
        const m = h.getNextSibling(), p = h.__key;
        ye(h.getWritable()), a.push(p), h = m;
      }
    }
    let d = u;
    for (const h of r) {
      d !== null && h.is(d) && (u = d = d.getPreviousSibling());
      const _ = h.getWritable();
      _.__parent === s && f--, ye(_);
      const m = h.__key;
      if (d === null) o.__first = m, _.__prev = null;
      else {
        const p = d.getWritable();
        p.__next = m, _.__prev = p.__key;
      }
      h.__key === s && b(76), _.__parent = s, l.push(m), d = h;
    }
    if (t + e === i)
      d !== null && (d.getWritable().__next = null, o.__last = d.__key);
    else if (c !== null) {
      const h = c.getWritable();
      if (d !== null) {
        const _ = d.getWritable();
        h.__prev = d.__key, _.__next = c.__key;
      } else h.__prev = null;
    }
    if (o.__size = f, a.length) {
      const h = M();
      if (w(h)) {
        const _ = new Set(a), m = new Set(l), { anchor: p, focus: g } = h;
        ua(p, _, m) && Mi(p, p.getNode(), this, u, c), ua(g, _, m) && Mi(g, g.getNode(), this, u, c), f !== 0 || this.canBeEmpty() || ot(this) || this.remove();
      }
    }
    return o;
  }
  getDOMSlot(t) {
    return new On(t);
  }
  exportDOM(t) {
    const { element: e } = super.exportDOM(t);
    if (F(e)) {
      const r = this.getIndent();
      r > 0 && (e.style.paddingInlineStart = 40 * r + "px", e.setAttribute("data-lexical-indent", String(r)));
      const i = this.getDirection();
      i && (e.dir = i);
    }
    return { element: e };
  }
  exportJSON() {
    const t = { children: [], direction: this.getDirection(), format: this.getFormatType(), indent: this.getIndent(), ...super.exportJSON() }, e = this.getTextFormat(), r = this.getTextStyle();
    return e === 0 && r === "" || ot(this) || this.getChildren().some(O) || (e !== 0 && (t.textFormat = e), r !== "" && (t.textStyle = r)), t;
  }
  updateFromJSON(t) {
    return super.updateFromJSON(t).setFormat(t.format).setIndent(t.indent).setDirection(t.direction).setTextFormat(t.textFormat || 0).setTextStyle(t.textStyle || "");
  }
  insertNewAfter(t, e) {
    return null;
  }
  canIndent() {
    return !0;
  }
  collapseAtStart(t) {
    return !1;
  }
  excludeFromCopy(t) {
    return !1;
  }
  canReplaceWith(t) {
    return !0;
  }
  canInsertAfter(t) {
    return !0;
  }
  canBeEmpty() {
    return !0;
  }
  canInsertTextBefore() {
    return !0;
  }
  canInsertTextAfter() {
    return !0;
  }
  isInline() {
    return !1;
  }
  isShadowRoot() {
    return !1;
  }
  canMergeWith(t) {
    return !1;
  }
  extractWithChild(t, e, r) {
    return !1;
  }
  canMergeWhenEmpty() {
    return !1;
  }
  reconcileObservedMutation(t, e) {
    const r = te(this, t, e);
    let i = r.getFirstChild();
    for (let o = this.getFirstChild(); o; o = o.getNextSibling()) {
      const s = e.getElementByKey(o.getKey());
      s !== null && (i == null ? (r.insertChild(s), i = s) : i !== s && r.replaceChild(s, i), i = i.nextSibling);
    }
  }
}
function S(n) {
  return n instanceof Nt;
}
function ua(n, t, e) {
  let r = n.getNode();
  for (; r; ) {
    const i = r.__key;
    if (t.has(i) && !e.has(i)) return !0;
    r = r.getParent();
  }
  return !1;
}
class Yn extends Kt {
  __slotHost;
  __slots;
  constructor(t) {
    super(t), this.__slotHost = null, this.__slots = null;
  }
  afterCloneFrom(t) {
    super.afterCloneFrom(t), this.__key === t.__key && (this.__slotHost = t.__slotHost, this.__slotHost !== null && this.__parent !== null && b(383, this.__key, String(this.__slotHost), String(this.__parent)), this.__slots = t.__slots);
  }
  decorate(t, e) {
    return null;
  }
  isIsolated() {
    return !1;
  }
  isInline() {
    return !0;
  }
  isKeyboardSelectable() {
    return !0;
  }
}
function W(n) {
  return n instanceof Yn;
}
class Zn extends Nt {
  __cachedText;
  static getType() {
    return "root";
  }
  static clone() {
    return new Zn();
  }
  constructor() {
    super("root"), this.__cachedText = null;
  }
  getTopLevelElementOrThrow() {
    b(51);
  }
  getTextContent() {
    const t = this.__cachedText;
    return t === null || !Bn() && j()._dirtyType !== 0 ? super.getTextContent() : t;
  }
  remove() {
    b(52);
  }
  replace(t) {
    b(53);
  }
  insertBefore(t) {
    b(54);
  }
  insertAfter(t) {
    b(55);
  }
  updateDOM(t, e) {
    return !1;
  }
  splice(t, e, r) {
    for (const i of r) S(i) || W(i) || b(282);
    return super.splice(t, e, r);
  }
  static importJSON(t) {
    return pt().updateFromJSON(t);
  }
  collapseAtStart() {
    return !0;
  }
}
function ut(n) {
  return n instanceof Zn;
}
function ju(n) {
  return new zr(Ac(n._nodeMap), null, n._slotsUsed);
}
function $s() {
  return new zr(/* @__PURE__ */ new Map([["root", new Zn()]]), null, !1);
}
function ss(n) {
  const t = n.exportJSON(), e = n.constructor;
  if (t.type !== e.getType() && b(130, e.name), S(n)) {
    const i = t.children;
    Array.isArray(i) || b(59, e.name);
    const o = n.getChildren();
    for (let s = 0; s < o.length; s++) {
      const l = ss(o[s]);
      i.push(l);
    }
  }
  const r = Xt(n);
  if (r.length > 0) {
    const i = {};
    for (const o of r) {
      const s = rn(n, o);
      s === null && b(366, e.name, o), i[o] = ss(s);
    }
    t.$slots = i;
  }
  return t;
}
function Lh(n) {
  return n instanceof zr;
}
class zr {
  _nodeMap;
  _selection;
  _flushSync;
  _readOnly;
  _parsed;
  _slotsUsed;
  constructor(t, e = null, r = !1) {
    this._nodeMap = t, this._selection = e || null, this._flushSync = !1, this._readOnly = !1, this._parsed = !1, this._slotsUsed = r;
  }
  isEmpty() {
    return this._nodeMap.size === 1 && this._selection === null;
  }
  read(t, e) {
    return la(e && e.editor || null, this, t);
  }
  clone(t) {
    const e = new zr(this._nodeMap, t === void 0 ? this._selection : t, this._slotsUsed);
    return e._readOnly = !0, e;
  }
  toJSON() {
    return la(null, this, () => ({ root: ss(pt()) }));
  }
}
class Fs extends Nt {
  static getType() {
    return "artificial";
  }
  createDOM(t) {
    return V().createElement("div");
  }
}
class Qn extends Kt {
  static getType() {
    return "linebreak";
  }
  static clone(t) {
    return new Qn(t.__key);
  }
  constructor(t) {
    super(t);
  }
  getTextContent() {
    return `
`;
  }
  createDOM() {
    return V().createElement("br");
  }
  updateDOM() {
    return !1;
  }
  isInline() {
    return !0;
  }
  static importDOM() {
    return { br: (t) => qu(t) || Vu(t) ? null : { conversion: $h, priority: 0 } };
  }
  static importJSON(t) {
    return Ke().updateFromJSON(t);
  }
}
function $h(n) {
  return { node: Ke() };
}
function Ke() {
  return Mt(new Qn());
}
function Pt(n) {
  return n instanceof Qn;
}
function qu(n) {
  const t = n.parentElement;
  if (t !== null && qn(t)) {
    const e = t.firstChild;
    if (e === n || e.nextSibling === n && Fi(e)) {
      const r = t.lastChild;
      if (r === n || r.previousSibling === n && Fi(r)) return !0;
    }
  }
  return !1;
}
function Vu(n) {
  const t = n.parentElement;
  if (t !== null && qn(t)) {
    const e = t.firstChild;
    if (e === n || e.nextSibling === n && Fi(e)) return !1;
    const r = t.lastChild;
    if (r === n || r.previousSibling === n && Fi(r)) return !0;
  }
  return !1;
}
function Fi(n) {
  return Ht(n) && /^( |\t|\r?\n)+$/.test(n.textContent || "");
}
class tr extends Nt {
  static getType() {
    return "paragraph";
  }
  static clone(t) {
    return new tr(t.__key);
  }
  createDOM(t) {
    const e = V().createElement("p"), r = $n(t.theme, "paragraph");
    return r !== void 0 && e.classList.add(...r), e;
  }
  updateDOM(t, e, r) {
    return !1;
  }
  static importDOM() {
    return { p: (t) => ({ conversion: Fh, priority: 0 }) };
  }
  exportDOM(t) {
    const { element: e } = super.exportDOM(t);
    if (F(e)) {
      this.isEmpty() && e.append(V().createElement("br"));
      const r = this.getFormatType();
      r && (e.style.textAlign = r);
    }
    return { element: e };
  }
  static importJSON(t) {
    return U().updateFromJSON(t);
  }
  exportJSON() {
    const t = super.exportJSON();
    if (t.textFormat === void 0 || t.textStyle === void 0) {
      const e = this.getChildren().find(O);
      e ? (t.textFormat = e.getFormat(), t.textStyle = e.getStyle()) : (t.textFormat = this.getTextFormat(), t.textStyle = this.getTextStyle());
    }
    return t;
  }
  insertNewAfter(t, e) {
    const r = U();
    r.setTextFormat(t.format), r.setTextStyle(t.style);
    const i = this.getDirection();
    return r.setDirection(i), r.setFormat(this.getFormatType()), r.setStyle(this.getStyle()), this.insertAfter(r, e), r;
  }
  collapseAtStart() {
    const t = this.getChildren();
    if (t.length === 0 || O(t[0]) && t[0].getTextContent().trim() === "") {
      if (this.getNextSibling() !== null) return this.selectNext(), this.remove(), !0;
      if (this.getPreviousSibling() !== null) return this.selectPrevious(), this.remove(), !0;
    }
    return !1;
  }
}
function Fh(n) {
  const t = U();
  if (fe(t, n), hn(n, t), t.getFormatType() === "") {
    const e = n.getAttribute("align");
    e && e && e in Ci && t.setFormat(e);
  }
  return ee(t, n), { node: t };
}
function U() {
  return Mt(new tr());
}
function to(n) {
  return n instanceof tr;
}
function Ph(n) {
  console.warn(n);
}
const R = 0, ar = 1, Ih = 4, Rh = -8;
function Gu(n, t, e, r, i) {
  const o = n._keyToDOMMap;
  o.clear(), n._editorState = $s(), n._pendingEditorState = r, n._compositionKey = null, n._dirtyType = 0, n._cloneNotNeeded.clear(), n._dirtyLeaves = /* @__PURE__ */ new Set(), n._dirtyElements.clear(), n._normalizedNodes = /* @__PURE__ */ new Set(), i && i.preserveUpdateQueue || (n._updateTags = /* @__PURE__ */ new Set(), n._updates = [], n._cascadeCount = 0), n._blockCursorElement = null, n._inputState.handledSelectionCommandTimeoutId !== null && clearTimeout(n._inputState.handledSelectionCommandTimeoutId), n._inputState = { collapsedSelectionFormat: { format: 0, key: "root", offset: 0, style: "", timeStamp: 0 }, compositionEndData: "", compositionPhase: "idle", hadOrphanedCompositionEvents: !1, handledSelectionCommandTimeoutId: null, isInsertLineBreak: !1, isInsertTextAfterHandledSelectionCommand: !1, isSelectionChangeFromDOMUpdate: !1, isSelectionChangeFromMouseDown: !1, lastBeforeInputInsertTextTimeStamp: 0, lastKeyCode: null, lastKeyDownTimeStamp: 0, postDeleteSelectionToRestore: null, unprocessedBeforeInputData: null };
  const s = n._observer;
  s !== null && (s.disconnect(), n._observer = null), t !== null && (t.textContent = "", (function(l, a) {
    const c = `__lexicalKey_${a._key}`;
    delete l[c];
  })(t, n)), e !== null && (e.textContent = "", o.set("root", e), ef(e, n, "root"));
}
function zh(n) {
  const t = /* @__PURE__ */ new Set(), e = /* @__PURE__ */ new Set();
  for (const { klass: r, ownNodeConfig: i } of qs(n)) {
    const o = r.transform;
    if (!e.has(o)) {
      e.add(o);
      const s = r.transform();
      s && t.add(s);
    }
    if (i) {
      const s = i.$transform;
      s && t.add(s);
    }
  }
  return t;
}
const Pi = { $createDOM: (n, t) => n.createDOM(t._config, t), $decorateDOM: (n, t, e, r) => {
}, $exportDOM: (n, t) => {
  const e = Is(t, n.getType());
  return e && e.exportDOM !== void 0 ? e.exportDOM(t, n) : n.exportDOM(t);
}, $extractWithChild: (n, t, e, r, i) => S(n) && n.extractWithChild(t, e, r), $getDOMSlot: (n, t, e) => n.getDOMSlot(t), $getSlotTargetElement: (n, t, e, r) => null, $shouldExclude: (n, t, e) => S(n) && n.excludeFromCopy("html"), $shouldInclude: (n, t, e) => !t || n.isSelected(t), $updateDOM: (n, t, e, r) => n.updateDOM(t, e, r._config) };
function Xu(n) {
  const t = n || {}, e = Uu(), r = t.theme || {}, i = n === void 0 ? e : t.parentEditor || null, o = t.disableEvents || !1, s = $s(), l = t.namespace || (i !== null ? i._config.namespace : of()), a = t.editorState, c = [Zn, We, Qn, Gn, tr, Fs, ...t.nodes || []], { onError: u, onWarn: f, html: d } = t, h = t.editable === void 0 || t.editable;
  let _;
  if (n === void 0 && e !== null) _ = e._nodes;
  else {
    _ = /* @__PURE__ */ new Map();
    for (let p = 0; p < c.length; p++) {
      let g = c[p], y = null, x = null;
      if (g && typeof g == "object") {
        const k = g;
        g = k.replace, y = k.with, x = k.withKlass || null;
      }
      if (typeof g != "function" || !g.prototype || !(g === Kt || g.prototype instanceof Kt)) {
        let k = "<unknown>";
        try {
          k = JSON.parse(Oc);
        } catch {
        }
        b(365, String(p - c.length + (t.nodes ? t.nodes.length : 0)), typeof g == "function" ? `${g.name}${typeof g.getType == "function" ? ` (type ${String(g.getType())})` : ""}` : String(g), String(k));
      }
      js(g);
      const v = g.getType(), E = zh(g);
      _.set(v, { exportDOM: d && d.export ? d.export.get(g) : void 0, klass: g, replace: y, replaceWithKlass: x, sharedNodeState: Wd(c[p]), transforms: E });
    }
  }
  const m = new Un(s, i, _, { disableEvents: o, dom: { ...Pi, ...n && n.dom }, namespace: l, theme: r }, u || console.error, f || Ph, (function(p, g) {
    const y = /* @__PURE__ */ new Map(), x = /* @__PURE__ */ new Set(), v = (E) => {
      Object.keys(E).forEach((k) => {
        let N = y.get(k);
        N === void 0 && (N = [], y.set(k, N)), N.push(E[k]);
      });
    };
    return p.forEach((E) => {
      const k = E.klass.importDOM;
      if (k == null || x.has(k)) return;
      x.add(k);
      const N = k.call(E.klass);
      N !== null && v(N);
    }), g && v(g), y;
  })(_, d ? d.import : void 0), h, n);
  return a !== void 0 && (m._pendingEditorState = a, m._dirtyType = 2), (function(p) {
    p.registerCommand(Xc, sh, R), p.registerCommand(Yc, lh, R), p.registerCommand(Zc, ah, R), p.registerCommand(Qc, ch, R), p.registerCommand(tu, uh, R);
  })(m), m;
}
function Wh(n, t) {
  const e = n.get(t);
  n.delete(t), e && e();
}
function cr(n, t, e) {
  return n.set(t, e), Wh.bind(null, n, t);
}
class Un {
  static version;
  _headless;
  _parentEditor;
  _rootElement;
  _editorState;
  _pendingEditorState;
  _compositionKey;
  _deferred;
  _keyToDOMMap;
  _updates;
  _updating;
  _cascadeCount;
  _listeners;
  _commands;
  _nodes;
  _decorators;
  _pendingDecorators;
  _config;
  _dirtyType;
  _cloneNotNeeded;
  _dirtyLeaves;
  _dirtyElements;
  _normalizedNodes;
  _updateTags;
  _observer;
  _key;
  _onError;
  _onWarn;
  _htmlConversions;
  _window;
  _editable;
  _blockCursorElement;
  _slotsUsed;
  _inputState;
  _createEditorArgs;
  constructor(t, e, r, i, o, s, l, a, c) {
    this._createEditorArgs = c, this._parentEditor = e, this._rootElement = null, this._editorState = t, this._pendingEditorState = null, this._compositionKey = null, this._deferred = [], this._keyToDOMMap = new Ko(), this._updates = [], this._updating = !1, this._cascadeCount = 0, this._listeners = { decorator: /* @__PURE__ */ new Map(), editable: /* @__PURE__ */ new Map(), mutation: /* @__PURE__ */ new Map(), root: /* @__PURE__ */ new Map(), textcontent: /* @__PURE__ */ new Map(), update: /* @__PURE__ */ new Map() }, this._commands = /* @__PURE__ */ new Map(), this._config = i, this._nodes = r, this._decorators = {}, this._pendingDecorators = null, this._dirtyType = 0, this._cloneNotNeeded = /* @__PURE__ */ new Set(), this._dirtyLeaves = /* @__PURE__ */ new Set(), this._dirtyElements = /* @__PURE__ */ new Map(), this._normalizedNodes = /* @__PURE__ */ new Set(), this._updateTags = /* @__PURE__ */ new Set(), this._observer = null, this._key = of(), this._onError = o, this._onWarn = s, this._htmlConversions = l, this._editable = a, this._headless = e !== null && e._headless, this._window = null, this._blockCursorElement = null, this._slotsUsed = !1, this._inputState = { collapsedSelectionFormat: { format: 0, key: "root", offset: 0, style: "", timeStamp: 0 }, compositionEndData: "", compositionPhase: "idle", hadOrphanedCompositionEvents: !1, handledSelectionCommandTimeoutId: null, isInsertLineBreak: !1, isInsertTextAfterHandledSelectionCommand: !1, isSelectionChangeFromDOMUpdate: !1, isSelectionChangeFromMouseDown: !1, lastBeforeInputInsertTextTimeStamp: 0, lastKeyCode: null, lastKeyDownTimeStamp: 0, postDeleteSelectionToRestore: null, unprocessedBeforeInputData: null };
  }
  isComposing() {
    return this._compositionKey != null;
  }
  registerUpdateListener(t) {
    return cr(this._listeners.update, t);
  }
  registerEditableListener(t) {
    return cr(this._listeners.editable, t);
  }
  registerDecoratorListener(t) {
    return cr(this._listeners.decorator, t);
  }
  registerTextContentListener(t) {
    return cr(this._listeners.textcontent, t);
  }
  registerRootListener(t) {
    const e = this._listeners.root;
    return an(cr(e, t, t(this._rootElement, null) || void 0), () => (function(r, i, o) {
      const s = r.get(i);
      s && s(), r.set(i, i(...o) || void 0);
    })(e, t, [null, this._rootElement]));
  }
  registerCommand(t, e, r) {
    r === void 0 && b(35);
    const i = this._commands;
    i.has(t) || i.set(t, [new sr(), new sr(), new sr(), new sr(), new sr()]);
    const o = i.get(t);
    o === void 0 && b(36, String(t));
    const s = (function(a) {
      return 7 & a;
    })(r), l = o[s];
    return s !== r ? l.addFront(e) : l.addBack(e), () => {
      l.delete(e), o.every((a) => a.size === 0) && i.delete(t);
    };
  }
  registerMutationListener(t, e, r) {
    const i = this.resolveRegisteredNodeAfterReplacements(this.getRegisteredNode(t)).klass, o = this._listeners.mutation;
    let s = o.get(e);
    s === void 0 && (s = /* @__PURE__ */ new Set(), o.set(e, s)), s.add(i);
    const l = r && r.skipInitialization;
    return l !== void 0 && l || this.initializeMutationListener(e, i), () => {
      s.delete(i), s.size === 0 && o.delete(e);
    };
  }
  getRegisteredNode(t) {
    const e = this._nodes.get(t.getType());
    return e === void 0 && b(37, t.name), e;
  }
  resolveRegisteredNodeAfterReplacements(t) {
    for (; t.replaceWithKlass; ) t = this.getRegisteredNode(t.replaceWithKlass);
    return t;
  }
  initializeMutationListener(t, e) {
    const r = this._editorState, i = ma(r).get(e.getType());
    if (!i) return;
    const o = /* @__PURE__ */ new Map();
    for (const s of i.keys()) o.set(s, "created");
    o.size > 0 && t(o, { dirtyLeaves: /* @__PURE__ */ new Set(), prevEditorState: r, updateTags: /* @__PURE__ */ new Set(["registerMutationListener"]) });
  }
  registerNodeTransformToKlass(t, e) {
    const r = this.getRegisteredNode(t);
    return r.transforms.add(e), r;
  }
  registerNodeTransform(t, e) {
    const r = this.registerNodeTransformToKlass(t, e), i = [r], o = r.replaceWithKlass;
    if (o != null) {
      const s = this.registerNodeTransformToKlass(o, e);
      i.push(s);
    }
    return (function(s, l) {
      const a = ma(s.getEditorState()), c = [];
      for (const u of l) {
        const f = a.get(u);
        f && c.push(f);
      }
      c.length !== 0 && s.update(() => {
        for (const u of c) for (const f of u.keys()) {
          const d = Z(f);
          d && d.markDirty();
        }
      }, s._pendingEditorState === null ? { tag: _r } : void 0);
    })(this, i.map((s) => s.klass.getType())), () => {
      i.forEach((s) => s.transforms.delete(e));
    };
  }
  hasNode(t) {
    return this._nodes.has(t.getType());
  }
  hasNodes(t) {
    return t.every(this.hasNode.bind(this));
  }
  dispatchCommand(t, e) {
    return L(this, t, e);
  }
  getDecorators() {
    return this._decorators;
  }
  getRootElement() {
    return this._rootElement;
  }
  getKey() {
    return this._key;
  }
  setRootElement(t) {
    const e = this._rootElement;
    if (t !== e) {
      const r = $n(this._config.theme, "root"), i = this._pendingEditorState || this._editorState;
      if (this._rootElement = t, Gu(this, e, t, i, { preserveUpdateQueue: !0 }), e !== null && (this._config.disableEvents || fh(e), r != null && e.classList.remove(...r)), t !== null) {
        const o = Hs(t), s = t.style;
        s.userSelect = "text", s.whiteSpace = "pre-wrap", s.wordBreak = "break-word", t.setAttribute("data-lexical-editor", "true"), this._window = o, this._dirtyType = 2, $c(this), this._updateTags.add(_r), Ae(this), this._config.disableEvents || (function(l, a) {
          const c = l.ownerDocument;
          Zo.set(l, c);
          let u = wi.get(c);
          u === void 0 && (u = { editors: /* @__PURE__ */ new Set(), hasShadowEditor: void 0 }, wi.set(c, u)), u.editors.add(a), u.hasShadowEditor = void 0, l.__lexicalEditor = a;
          const f = Su(l);
          f.push(oh.register(c));
          for (let d = 0; d < Yo.length; d++) {
            const [h, _] = Yo[d], m = typeof _ == "function" ? (p) => {
              Ul(p) || (Bl(p), (a.isEditable() || h === "click") && _(p, a));
            } : (p) => {
              if (Ul(p)) return;
              Bl(p);
              const g = a.isEditable();
              switch (h) {
                case "cut":
                  return g && L(a, As, p);
                case "copy":
                  return L(a, Xi, p);
                case "paste":
                  return g && L(a, Es, p);
                case "dragstart":
                  return g && L(a, hu, p);
                case "dragover":
                  return g && L(a, gu, p);
                case "dragend":
                  return g && L(a, Yd, p);
                case "focus":
                  return g && L(a, th, p);
                case "blur":
                  return g && L(a, eh, p);
                case "drop":
                  return g && L(a, fu, p);
              }
            };
            f.push(ih(l, h, m));
          }
        })(t, this), r != null && t.classList.add(...r);
      } else this._window = null, this._updateTags.add(_r), Ae(this);
      mr("root", this, !1, t, e);
    }
  }
  getElementByKey(t) {
    return this._keyToDOMMap.get(t) || null;
  }
  getEditorState() {
    return this._editorState;
  }
  setEditorState(t, e) {
    t.isEmpty() && b(38);
    let r = t;
    r._readOnly && (r = ju(t), r._selection = t._selection ? t._selection.clone() : null), Lc(this);
    const i = this._pendingEditorState, o = e !== void 0 ? e.tag : null;
    i === null || i.isEmpty() || (o != null && this._updateTags.add(o), Ae(this)), this._pendingEditorState = r, this._dirtyType = 2, this._dirtyElements.set("root", !1), this._compositionKey = null, this._slotsUsed = this._slotsUsed || t._slotsUsed, Gt(this, () => {
      if (o && this._updateTags.add(o), t._parsed) for (const [s, l] of r._nodeMap.entries()) S(l) ? this._dirtyElements.set(s, !0) : this._dirtyLeaves.add(s);
    }, { discrete: !this._updating || void 0 });
  }
  parseEditorState(t, e) {
    return (function(r, i, o) {
      const s = $s(), l = Ct, a = $t, c = ht, u = i._dirtyElements, f = i._dirtyLeaves, d = i._cloneNotNeeded, h = i._dirtyType;
      i._dirtyElements = /* @__PURE__ */ new Map(), i._dirtyLeaves = /* @__PURE__ */ new Set(), i._cloneNotNeeded = /* @__PURE__ */ new Set(), i._dirtyType = 0, Ct = s, $t = !1, ht = i, Ps(null);
      try {
        const _ = i._nodes;
        $i(r.root, _), o && o(), s._readOnly = !0, s._parsed = !0;
      } catch (_) {
        _ instanceof Error && i._onError(_);
      } finally {
        i._dirtyElements = u, i._dirtyLeaves = f, i._cloneNotNeeded = d, i._dirtyType = h, Ct = l, $t = a, ht = c;
      }
      return s;
    })(typeof t == "string" ? JSON.parse(t) : t, this, e);
  }
  read(...t) {
    const [e, r] = t.length === 1 ? ["force-commit", t[0]] : t;
    return e === "force-commit" && Ae(this), (e === "pending" ? this._pendingEditorState || this._editorState : this.getEditorState()).read(r, { editor: this });
  }
  update(t, e) {
    (function(r, i, o) {
      r._updating ? r._updates.push([i, o]) : Qi(r, i, o);
    })(this, t, e);
  }
  focus(t, e = {}) {
    const r = this._rootElement;
    r !== null && (r.setAttribute("autocapitalize", "off"), Gt(this, () => {
      const i = M(), o = pt();
      i !== null ? i.dirty || kt(i.clone()) : o.getChildrenSize() !== 0 && (e.defaultSelection === "rootStart" ? o.selectStart() : o.selectEnd()), Jn("focus"), qh(() => {
        r.removeAttribute("autocapitalize"), t && t();
      });
    }), this._pendingEditorState === null && r.removeAttribute("autocapitalize"));
  }
  blur() {
    const t = this._rootElement;
    t !== null && t.blur();
    const e = Dt(this._window);
    e !== null && e.removeAllRanges();
  }
  isEditable() {
    return this._editable;
  }
  setEditable(t) {
    this._editable !== t && (this._editable = t, mr("editable", this, !0, t), this._slotsUsed && this.update(() => Ah()));
  }
  toJSON() {
    return { editorState: this._editorState.toJSON() };
  }
}
Un.version = Oc;
let ls = null;
function Ps(n) {
  ls = n;
}
let Kh = 1;
function Yu(n, t) {
  const e = Is(n, t);
  return e === void 0 && b(30, t), e;
}
function Is(n, t) {
  return n._nodes.get(t);
}
const Bh = typeof queueMicrotask == "function" ? queueMicrotask : (n) => {
  Promise.resolve().then(n);
};
function Rs(n, t) {
  const e = t !== void 0 ? t : (() => {
    const o = n.getRootNode();
    return fn(o) || be(o) ? Mr(o) : null;
  })();
  if (!F(e) || e.hasAttribute("data-lexical-slot")) return !1;
  const r = ve(e), i = e.nodeName;
  return vu(r) && (i === "INPUT" || i === "TEXTAREA" || e.contentEditable === "true" && Kr(e) == null);
}
function Wr(n, t, e) {
  const r = n.getRootElement();
  if (!r) return !1;
  try {
    if (!t || !r.contains(t) || !r.contains(e)) return !1;
  } catch {
    return !1;
  }
  return Ln(t) === n && n.read("latest", () => !Rs(t));
}
function eo(n) {
  return n instanceof Un;
}
function Ln(n) {
  let t = n;
  for (; t != null; ) {
    const e = Kr(t);
    if (eo(e)) return e;
    t = dn(t);
  }
  return null;
}
function Kr(n) {
  return n ? n.__lexicalEditor : null;
}
function Xe(n) {
  return Ar(n) || n.isToken();
}
function zt(n) {
  return Xe(n) || n.isSegmented();
}
function Ht(n) {
  return nr(n) && n.nodeType === 3;
}
function fn(n) {
  return nr(n) && n.nodeType === 9;
}
function Uh(n) {
  let t = n;
  for (; t != null; ) {
    if (Ht(t)) return t;
    t = t.firstChild;
  }
  return null;
}
function en(n, t, e) {
  const r = Ie[t];
  if (e !== null && (n & r) === (e & r)) return n;
  let i = n ^ r;
  return t === "subscript" ? i &= -65 : t === "superscript" ? i &= -33 : t === "lowercase" ? (i &= -513, i &= -1025) : t === "uppercase" ? (i &= -257, i &= -1025) : t === "capitalize" && (i &= -257, i &= -513), i;
}
function Zu(n) {
  return O(n) || Pt(n) || W(n);
}
function Qu(n, t) {
  const e = (function() {
    const s = ls;
    return ls = null, s;
  })();
  if ((t = t || e && e.__key) != null) return void (n.__key = t);
  xt(), Ku();
  const r = j(), i = Ne(), o = "" + Kh++;
  i._nodeMap.set(o, n), S(n) ? r._dirtyElements.set(o, !0) : r._dirtyLeaves.add(o), r._cloneNotNeeded.add(o), r._dirtyType === 0 && (r._dirtyType = 1), n.__key = o;
}
function ye(n) {
  Tt(n) !== null && b(380, n.__key, String(Tt(n)));
  const t = n.getParent();
  if (t !== null) {
    const e = n.getWritable(), r = t.getWritable(), i = n.getPreviousSibling(), o = n.getNextSibling(), s = o !== null ? o.__key : null, l = i !== null ? i.__key : null, a = i !== null ? i.getWritable() : null, c = o !== null ? o.getWritable() : null;
    i === null && (r.__first = s), o === null && (r.__last = l), a !== null && (a.__next = s), c !== null && (c.__prev = l), e.__prev = null, e.__next = null, e.__parent = null, r.__size--;
  }
}
function Ii(n) {
  Ku(), Ni(n) && b(323, n.__key, n.__type);
  const t = n.getLatest(), e = t.__parent !== null ? t.__parent : nn(t) ? t.__slotHost : null, r = Ne(), i = j(), o = r._nodeMap, s = i._dirtyElements;
  e !== null && (function(a, c, u) {
    let f = a;
    for (; f !== null; ) {
      if (u.has(f)) return;
      const d = c.get(f);
      if (d === void 0) break;
      u.set(f, !1), f = d.__parent !== null ? d.__parent : nn(d) ? d.__slotHost : null;
    }
  })(e, o, s);
  const l = t.__key;
  i._dirtyType === 0 && (i._dirtyType = 1), S(n) ? s.set(l, !0) : i._dirtyLeaves.add(l);
}
function St(n) {
  xt();
  const t = j(), e = t._compositionKey;
  if (n !== e) {
    if (t._compositionKey = n, e !== null) {
      const r = Z(e);
      r !== null && r.getWritable();
    }
    if (n !== null) {
      const r = Z(n);
      r !== null && r.getWritable();
    }
  }
}
function xe() {
  return Bn() ? null : j()._compositionKey;
}
function Z(n, t) {
  const e = (t || Ne())._nodeMap.get(n);
  return e === void 0 ? null : e;
}
function tf(n, t) {
  const e = er(n, j());
  return e !== void 0 ? Z(e, t) : null;
}
function ef(n, t, e) {
  n[`__lexicalKey_${t._key}`] = e;
}
function er(n, t) {
  return n[`__lexicalKey_${t._key}`];
}
function ve(n, t) {
  let e = n;
  for (; e != null; ) {
    const r = tf(e, t);
    if (r !== null) return r;
    e = dn(e);
  }
  return null;
}
function nf(n) {
  const t = n._decorators, e = Object.assign({}, t);
  return n._pendingDecorators = e, e;
}
function fa(n) {
  return n.read(() => pt().getTextContent());
}
function pt() {
  return Ne()._nodeMap.get("root");
}
function kt(n) {
  xt();
  const t = Ne();
  n !== null && (n.dirty = !0, n.setCachedNodes(null), w(n) && j()._slotsUsed && Pu(n)), t._selection = n;
}
function da() {
  xt(), Lc(j());
}
function kn(n) {
  const t = (function(e, r) {
    let i = e;
    for (; i != null; ) {
      const o = er(i, r);
      if (o !== void 0) return o;
      i = dn(i);
    }
    return null;
  })(n, j());
  return t === null ? null : Z(t);
}
function rf(n) {
  return /[\uD800-\uDBFF][\uDC00-\uDFFF]/g.test(n);
}
function zs(n) {
  const t = [];
  for (let e = n; e !== null; e = e._parentEditor) t.push(e);
  return t;
}
function of() {
  return Math.random().toString(36).replace(/[^a-z]+/g, "").substring(0, 5);
}
function sf(n) {
  return Ht(n) ? n.nodeValue : null;
}
function Ws(n, t, e) {
  const r = Dt(It(t));
  if (r === null) return;
  const i = Qt(r, t._rootElement), o = i.anchorNode;
  let { anchorOffset: s, focusOffset: l } = i;
  if (o !== null) {
    let a = sf(o);
    const c = ve(o);
    if (a !== null && O(c)) {
      if ((a === Sr || a === ks) && e) {
        const u = e.length;
        a = e, s = u, l = u;
      }
      a !== null && Ks(c, a, s, l, n);
    }
  }
}
function Ks(n, t, e, r, i) {
  let o = n;
  if (o.isAttached() && (i || !o.isDirty())) {
    const s = o.isComposing();
    if (o.isToken() && s) return;
    let l = t;
    if ((s || i) && (t.endsWith(Sr) && (l = t.slice(0, -Sr.length)), i)) {
      const c = ks;
      let u;
      for (; (u = l.indexOf(c)) !== -1; ) l = l.slice(0, u) + l.slice(u + c.length), e !== null && e > u && (e = Math.max(u, e - c.length)), r !== null && r > u && (r = Math.max(u, r - c.length));
    }
    const a = o.getTextContent();
    if (i || l !== a) {
      const c = M();
      if (l === "") {
        if (St(null), Ir || Pe || Rr) o.remove();
        else {
          const m = j();
          wo(o, "", c), setTimeout(() => {
            m.update(() => {
              o.isAttached() && o.getTextContent() === "" && o.remove();
            });
          }, 20);
        }
        return;
      }
      const u = o.getParent(), f = Xn(), d = o.getTextContentSize(), h = xe(), _ = o.getKey();
      if (o.isToken() && !s || h !== null && _ === h && !s || w(f) && (u !== null && !u.canInsertTextBefore() && f.anchor.offset === 0 || f.anchor.key === n.__key && f.anchor.offset === 0 && !o.canInsertTextBefore() && !s || f.focus.key === n.__key && f.focus.offset === d && !o.canInsertTextAfter() && !s)) return void o.markDirty();
      if (!w(c) || e === null || r === null) return void wo(o, l, c);
      if (c.setTextNodeRange(o, e, o, r), o.isSegmented()) {
        const m = mt(o.getTextContent());
        o.replace(m), o = m;
      }
      wo(o, l, c);
    }
  }
}
function wo(n, t, e) {
  if (n.setTextContent(t), w(e)) {
    const r = n.getKey();
    let i = !1;
    for (const o of ["anchor", "focus"]) {
      const s = e[o];
      s.type === "text" && s.key === r && (s.offset = ae(n, s.offset, "clamp"), i = !0);
    }
    i && (e._cachedNodes = null, e._cachedIsBackward = null);
  }
}
function ti(n, t, e) {
  const r = t[e] || !1;
  return r === "any" || r === n[e];
}
function Hh(n, t) {
  return ti(n, t, "altKey") && ti(n, t, "ctrlKey") && ti(n, t, "shiftKey") && ti(n, t, "metaKey");
}
function Y(n, t, e) {
  if (!Hh(n, e)) return !1;
  if (n.key.toLowerCase() === t.toLowerCase()) return !0;
  if (t.length > 1 || n.key.length === 1 && n.key.charCodeAt(0) <= 127) return !1;
  if (n.code.startsWith("Digit") && /^\d$/.test(t)) return n.code === `Digit${t}`;
  const r = "Key" + t.toUpperCase();
  return n.code === r;
}
const pe = { ctrlKey: !ne, metaKey: ne }, ha = { altKey: ne, ctrlKey: !ne };
function ga(n) {
  return n.key === "Backspace";
}
function Jh(n) {
  const t = pt();
  if (w(n)) {
    const e = n.anchor, r = n.focus, i = e.getNode();
    if (ut(i)) return e.set(i.getKey(), 0, "element"), r.set(i.getKey(), i.getChildrenSize(), "element"), wn(n), n;
    const o = i.getTopLevelElementOrThrow(), s = o.getParent();
    if (s === null) return S(o) && (e.set(o.getKey(), 0, "element"), r.set(o.getKey(), o.getChildrenSize(), "element"), wn(n)), n;
    const l = s;
    return e.set(l.getKey(), 0, "element"), r.set(l.getKey(), l.getChildrenSize(), "element"), wn(n), n;
  }
  {
    const e = t.select(0, t.getChildrenSize());
    return kt(wn(e)), e;
  }
}
function $n(n, t) {
  n.__lexicalClassNameCache === void 0 && (n.__lexicalClassNameCache = {});
  const e = n.__lexicalClassNameCache, r = e[t];
  if (r !== void 0) return r;
  const i = n[t];
  if (typeof i == "string") {
    const o = Ce(i);
    return e[t] = o, o;
  }
  return i;
}
function Bs(n, t, e, r, i) {
  if (e.size === 0) return;
  const o = r.__type, s = r.__key, l = t.get(o);
  l === void 0 && b(33, o);
  const a = l.klass;
  let c = n.get(a);
  c === void 0 && (c = /* @__PURE__ */ new Map(), n.set(a, c));
  const u = c.get(s), f = u === "destroyed" && i === "created";
  (u === void 0 || f) && c.set(s, f ? "updated" : i);
}
function pa(n, t, e) {
  const r = n.getParent();
  let i = e, o = n;
  return r !== null && (e === 0 && (i = o.getIndexWithinParent(), o = r)), o.getChildAtIndex(i - 1);
}
function jh(n, t) {
  const e = n.offset;
  if (n.type === "element")
    return pa(n.getNode(), t, e);
  {
    const r = n.getNode();
    if (e === 0 || !t) {
      const i = r.getPreviousSibling();
      return i === null ? pa(r.getParentOrThrow(), t, r.getIndexWithinParent() + 0) : i;
    }
  }
  return null;
}
function lf(n) {
  const t = It(n).event, e = t && t.inputType;
  return e === "insertFromPaste" || e === "insertFromPasteAsQuotation";
}
function L(n, t, e) {
  return Ju(n, t, e, n);
}
function Hn(n, t) {
  const e = n._keyToDOMMap.get(t);
  return e === void 0 && b(75, t), e;
}
function dn(n) {
  const t = n.assignedSlot || n.parentElement;
  if (t !== null) return t;
  const e = n.parentNode;
  return be(e) ? e.host : null;
}
function Us(n) {
  return fn(n) ? n : F(n) ? n.ownerDocument : null;
}
function Jn(n) {
  xt(), j()._updateTags.add(n);
}
function qh(n) {
  xt(), j()._deferred.push(n);
}
function jn(n, t) {
  let e = n.getParent();
  for (; e !== null; ) {
    if (e.is(t)) return !0;
    e = e.getParent();
  }
  return !1;
}
function Hs(n) {
  const t = Us(n);
  return t ? t.defaultView : null;
}
function It(n) {
  const t = n._window;
  return t === null && b(78), t;
}
function Js(n) {
  return S(n) && n.isInline() || W(n) && n.isInline();
}
function af(n) {
  let t = n.getLatest();
  for (; t !== null; ) {
    if (Tt(t) !== null && S(t)) return t;
    const e = t.getParentOrThrow();
    if (ot(e)) return e;
    t = e;
  }
  return t;
}
function Fn(n) {
  return S(n) && n.isShadowRoot();
}
function ot(n) {
  return ut(n) || Fn(n);
}
function At(n, t = !1) {
  const e = n.constructor.clone(n);
  return Qu(e, null), e.afterCloneFrom(n), t || e.resetOnCopyNodeFrom(n), e;
}
function Mt(n) {
  const t = j(), e = n.getType(), r = Is(t, e);
  r === void 0 && b(200, n.constructor.name, e);
  const { replace: i, replaceWithKlass: o } = r;
  if (i !== null) {
    const s = i(n), l = s.constructor;
    return o !== null ? s instanceof o || b(201, o.name, o.getType(), l.name, l.getType(), n.constructor.name, e) : s instanceof n.constructor && l !== n.constructor || b(202, l.name, l.getType(), n.constructor.name, e), s.__key === n.__key && b(203, n.constructor.name, e, l.name, l.getType()), s;
  }
  return n;
}
function ko(n, t) {
  !ut(n.getParent()) || S(t) || W(t) || b(99);
}
function Vh(n) {
  const t = Z(n);
  return t === null && b(63, n), t;
}
function mi(n) {
  if (!n || n.isInline()) return !1;
  if (W(n)) return !0;
  if (S(n)) {
    if (n.isShadowRoot()) {
      const t = n.getParent();
      return !(S(t) && t.isShadowRoot());
    }
    return !n.canBeEmpty();
  }
  return !1;
}
function Ri(n, t, e) {
  e.style.removeProperty("caret-color"), t._blockCursorElement = null;
  const r = n.parentElement;
  r !== null && r.removeChild(n);
}
function Dt(n) {
  return se ? (n || window).getSelection() : null;
}
function Gh(n) {
  const t = Hs(n);
  return t ? t.getSelection() : null;
}
function be(n) {
  return cs(n) && "host" in n;
}
const Xh = [];
function cf(n) {
  const t = n.getRootNode();
  if (t === n || !be(t)) return Xh;
  const e = [t];
  let r = t.host;
  for (; ; ) {
    const i = r.getRootNode();
    if (i === r || !be(i)) break;
    e.push(i), r = i.host;
  }
  return e;
}
function* uf(n) {
  const t = [n];
  let e;
  for (; e = t.pop(); ) {
    yield* e.querySelectorAll('[data-lexical-editor="true"]');
    const r = (fn(e) ? e : e.ownerDocument).createTreeWalker(e, NodeFilter.SHOW_ELEMENT);
    let i;
    for (; i = r.nextNode(); ) i.shadowRoot && t.push(i.shadowRoot);
  }
}
function ff(n) {
  return n !== null ? n.ownerDocument : document;
}
function V() {
  const n = Uu();
  return ff(n !== null ? n._rootElement : null);
}
function no(n, t) {
  if (t === null || typeof n.getComposedRanges != "function") return null;
  const e = cf(t);
  if (e.length === 0) return null;
  const r = n.getComposedRanges;
  try {
    const i = r.call(n, { shadowRoots: e })[0];
    if (i !== void 0) return i;
  } catch {
  }
  try {
    const i = r.apply(n, e)[0];
    if (i !== void 0) return i;
  } catch {
  }
  return null;
}
function Yh(n, t) {
  const e = no(n, t);
  if (e !== null) {
    const r = Zh(e);
    if (r !== null) return r;
  }
  return n.rangeCount > 0 ? n.getRangeAt(0) : null;
}
function Qt(n, t) {
  const e = no(n, t);
  return e === null ? n : Qh(e, tg(n));
}
function Zh(n) {
  const t = n.startContainer.ownerDocument;
  if (t === null) return null;
  const e = t.createRange();
  try {
    return e.setStart(n.startContainer, n.startOffset), e.setEnd(n.endContainer, n.endOffset), e;
  } catch {
    return null;
  }
}
function Qh(n, t) {
  const { startContainer: e, startOffset: r, endContainer: i, endOffset: o } = n;
  return t === "backward" ? { anchorNode: i, anchorOffset: o, direction: t, focusNode: e, focusOffset: r } : { anchorNode: e, anchorOffset: r, direction: t, focusNode: i, focusOffset: o };
}
function tg(n) {
  return n.direction;
}
function as(n) {
  const t = n.getRootNode();
  return fn(t) || be(t) ? t.activeElement : null;
}
function Mr(n) {
  let t = n.activeElement;
  for (; t !== null && t.shadowRoot !== null; ) {
    const e = t.shadowRoot.activeElement;
    if (e === null) break;
    t = e;
  }
  return t;
}
function df(n) {
  const t = n.target;
  if (t !== null && F(t) && t.shadowRoot !== null && typeof n.composedPath == "function") {
    const e = n.composedPath();
    if (e.length > 0) return e[0];
  }
  return t;
}
function hf(n) {
  return F(n) && n.tagName === "A";
}
function eg(n) {
  return F(n) && n.tagName === "TR";
}
function F(n) {
  return nr(n) && n.nodeType === 1;
}
function nr(n) {
  return typeof n == "object" && n !== null && "nodeType" in n && typeof n.nodeType == "number";
}
function cs(n) {
  return nr(n) && n.nodeType === 11;
}
const ng = /^(a|abbr|acronym|b|cite|code|del|em|i|ins|kbd|label|mark|output|q|ruby|s|samp|span|strong|sub|sup|time|u|tt|var|#text)$/i;
function zi(n) {
  return !(!F(n) || !n.style.display.startsWith("inline")) || ng.test(n.nodeName);
}
const rg = /^(address|article|aside|blockquote|canvas|dd|div|dl|dt|fieldset|figcaption|figure|footer|form|h1|h2|h3|h4|h5|h6|header|hr|li|main|nav|noscript|ol|p|pre|section|table|td|tfoot|ul|video)$/i;
function qn(n) {
  return (!F(n) || !n.style.display.startsWith("inline")) && rg.test(n.nodeName);
}
function bt(n) {
  if (W(n) && !n.isInline()) return !0;
  if (!S(n) || ot(n)) return !1;
  const t = n.getFirstChild(), e = t === null || Pt(t) || O(t) || t.isInline();
  return !n.isInline() && n.canBeEmpty() !== !1 && e;
}
function st() {
  return j();
}
function ro(n = st()) {
  return n._config.dom || Pi;
}
function te(n, t, e = st()) {
  const r = ro(e).$getDOMSlot(n, t, e);
  return S(n) && (ig(r) || b(344, n.getKey(), n.getType())), r;
}
function ig(n) {
  return n instanceof On;
}
function $e(n, t, e = st()) {
  return Uh(te(n, t, e).element);
}
const _a = /* @__PURE__ */ new WeakMap(), og = /* @__PURE__ */ new Map();
function ma(n) {
  if (!n._readOnly && n.isEmpty()) return og;
  n._readOnly || b(192);
  let t = _a.get(n);
  return t || (t = (function(e) {
    const r = /* @__PURE__ */ new Map();
    for (const [i, o] of e._nodeMap) {
      const s = o.__type;
      let l = r.get(s);
      l || (l = /* @__PURE__ */ new Map(), r.set(s, l)), l.set(i, o);
    }
    return r;
  })(n), _a.set(n, t)), t;
}
function gf(n) {
  const t = n.constructor.clone(n);
  return t.afterCloneFrom(n), t;
}
function sg(n) {
  return (t = gf(n))[Cu] = !0, t;
  var t;
}
function hn(n, t) {
  const e = n.getAttribute("data-lexical-indent");
  if (e !== null) {
    const o = parseInt(e, 10);
    if (Number.isFinite(o) && o >= 0) return void t.setIndent(o);
  }
  const r = parseInt(n.style.paddingInlineStart, 10) || 0, i = Math.round(r / 40);
  t.setIndent(i);
}
function ee(n, t) {
  const e = t.getAttribute("dir");
  return e === "ltr" || e === "rtl" ? n.setDirection(e) : n;
}
function fe(n, t) {
  const e = t.style.textAlign;
  return e && e in Ci ? n.setFormat(e) : n;
}
function pf(n, t) {
  n.__lexicalUnmanaged = !0, t && t.captureSelection !== void 0 && (n.__lexicalCapturedSelection = t.captureSelection);
}
function _f(n) {
  return n.__lexicalUnmanaged === !0;
}
function lg(n, t = st()) {
  const e = t.isEditable();
  n.contentEditable = e ? "true" : "false", e ? n.__lexicalEditor = t : delete n.__lexicalEditor;
}
function Wi(n, t) {
  let e = n;
  for (; e != null; ) {
    if (e.__lexicalCapturedSelection === !0) return !0;
    if (F(e) && e.hasAttribute("data-lexical-slot") || er(e, t) !== void 0) return !1;
    e = dn(e);
  }
  return !1;
}
function ur(n, t) {
  return (function(e, r) {
    return Object.prototype.hasOwnProperty.call(e, r);
  })(n, t) && n[t] !== Kt[t];
}
const ya = /* @__PURE__ */ new WeakMap();
function js(n) {
  const t = ya.get(n);
  if (t) return t;
  const e = n.prototype != null && ml in n.prototype ? n.prototype[ml]() : void 0, r = (function(a) {
    if (!(a === Kt || a.prototype instanceof Kt)) {
      let c = "<unknown>", u = "<unknown>";
      try {
        c = a.getType();
      } catch {
      }
      try {
        Un.version && (u = JSON.parse(Un.version));
      } catch {
      }
      b(290, a.name, c, u);
    }
    return a === Yn || a === Nt || a === Kt;
  })(n), i = !r && ur(n, "getType") ? n.getType() : void 0;
  let o, s = i;
  if (e) if (i) o = e[i];
  else {
    for (const [a, c] of Object.entries(e)) s = a, o = c;
    if (!o) for (const a of Object.getOwnPropertySymbols(e)) {
      const c = e[a];
      if (c) {
        o = c;
        break;
      }
    }
  }
  if (!r && s && (ur(n, "getType") || (n.getType = () => s), ur(n, "clone") || (n.clone = (a) => (Ps(a), new n())), ur(n, "importJSON") || (n.importJSON = o && o.$importJSON || ((a) => new n().updateFromJSON(a))), !ur(n, "importDOM") && o)) {
    const { importDOM: a } = o;
    a && (n.importDOM = () => a);
  }
  const l = { klass: n, ownNodeConfig: o, ownNodeType: s };
  return ya.set(n, l), l;
}
function* qs(n) {
  for (let t = n; t && (t === Kt || vu(t.prototype)); ) {
    const e = js(t);
    yield e, t = e.ownNodeConfig && e.ownNodeConfig.extends || mf(t);
  }
}
function ag(n) {
  const t = st();
  return xt(), new (t.resolveRegisteredNodeAfterReplacements(t.getRegisteredNode(n))).klass();
}
const dt = (n, t) => {
  let e = n;
  for (; e != null && !ut(e); ) {
    if (t(e)) return e;
    e = e.getParent();
  }
  return null;
};
function Ki(n, t) {
  const e = [];
  let r = n.__first;
  for (; r !== null; ) {
    const i = t === null ? Z(r) : t.get(r);
    i == null && b(174), e.push(r), r = i.__next;
  }
  return e;
}
function mf(n) {
  const t = Object.getPrototypeOf(n);
  if (typeof t == "function" && t !== Function.prototype) return t;
  const e = n.prototype && Object.getPrototypeOf(n.prototype);
  return e ? e.constructor : null;
}
const yf = /* @__PURE__ */ new Map();
function Se(n) {
  return S(n) || W(n);
}
function nn(n) {
  return S(n) || W(n);
}
function Tt(n) {
  const t = n.getLatest();
  return nn(t) ? t.__slotHost : null;
}
function ie(n) {
  const t = Tt(n);
  if (t === null) return null;
  const e = Z(t);
  return S(e) || W(e) || b(370), e;
}
function cg(n) {
  const t = ie(n);
  if (t === null) return null;
  const e = n.getLatest().__key;
  for (const [r, i] of oo(t)) if (i === e) return r;
  return null;
}
function io(n) {
  let t = n.getLatest();
  for (; t !== null; ) {
    if (Tt(t) !== null) return t;
    t = t.getParent();
  }
  return null;
}
function oo(n) {
  const t = n.getLatest();
  return Se(t) && t.__slots !== null ? t.__slots : yf;
}
function Xt(n) {
  return Array.from(oo(n).keys());
}
function rn(n, t) {
  const e = oo(n).get(t);
  return e === void 0 ? null : Z(e);
}
const ug = ["__proto__", "constructor", "prototype"], xa = /* @__PURE__ */ Symbol("slotMapOwner");
function us(n) {
  let t = n.__slots;
  return t !== null && t[xa] === n || (t = new Map(t), t[xa] = n, n.__slots = t), t;
}
const Sa = /* @__PURE__ */ new WeakMap(), fg = [];
function dg(n) {
  for (const { ownNodeConfig: t } of qs(n)) {
    const e = t && t.slots;
    if (e) return e;
  }
  return fg;
}
function xf(n) {
  let t = "";
  for (const e of Xt(n)) {
    const r = rn(n, e);
    r !== null && (t += r.getTextContent());
  }
  return t;
}
function Ca(n, t, e) {
  const r = e.get(n), i = e.get(t);
  return r !== void 0 ? i !== void 0 ? r - i : -1 : i !== void 0 ? 1 : n < t ? -1 : n > t ? 1 : 0;
}
function hg(n) {
  const t = n.__slots;
  if (t === null || t.size < 2) return;
  const e = (function(s) {
    let l = Sa.get(s);
    if (l === void 0) {
      const a = dg(s), c = /* @__PURE__ */ new Map();
      for (const u of a) ug.includes(u) && b(371, s.name, u), c.has(u) && b(372, s.name, u), c.set(u, c.size);
      l = c, Sa.set(s, l);
    }
    return l;
  })(n.constructor);
  let r = null, i = !0;
  for (const s of t.keys()) {
    if (r !== null && Ca(r, s, e) > 0) {
      i = !1;
      break;
    }
    r = s;
  }
  if (i) return;
  const o = Array.from(t).sort(([s], [l]) => Ca(s, l, e));
  t.clear();
  for (const [s, l] of o) t.set(s, l);
}
function Sf(n, t, e) {
  t !== "__proto__" && t !== "constructor" && t !== "prototype" || b(373, t);
  const r = n.getLatest();
  if (r.__slots !== null && r.__slots.get(t) === e.getLatest().__key) return r;
  (!S(e) && !W(e) || e.isInline()) && b(374, e.__key);
  const i = n.getWritable(), o = us(i), s = o.get(t);
  s !== void 0 && Cf(s);
  const l = e.getWritable(), a = ie(l);
  if (a !== null) {
    const c = cg(l);
    c !== null && us(a.getWritable()).delete(c), l.__slotHost = null;
  }
  return ye(l), l.__slotHost = i.__key, o.set(t, l.__key), hg(i), (function() {
    const c = st();
    c._slotsUsed = !0, c._pendingEditorState && (c._pendingEditorState._slotsUsed = !0);
  })(), i;
}
function gg(n, t) {
  const e = n.getWritable();
  if (e.__slots === null) return e;
  const r = e.__slots.get(t);
  return r !== void 0 && (Cf(r), us(e).delete(t)), e;
}
function Cf(n) {
  const t = Z(n);
  if (t === null) return;
  const e = t.getWritable();
  nn(e) || b(377, n), e.__slotHost = null, e.remove();
}
const pg = { next: "previous", previous: "next" };
class Vs {
  origin;
  constructor(t) {
    this.origin = t;
  }
  [Symbol.iterator]() {
    return Zs({ hasNext: Te, initial: this.getAdjacentCaret(), map: (t) => t, step: (t) => t.getAdjacentCaret() });
  }
  getAdjacentCaret() {
    return G(this.getNodeAtCaret(), this.direction);
  }
  getSiblingCaret() {
    return G(this.origin, this.direction);
  }
  remove() {
    const t = this.getNodeAtCaret();
    return t && t.remove(), this;
  }
  replaceOrInsert(t, e) {
    const r = this.getNodeAtCaret();
    return t.is(this.origin) || t.is(r) || (r === null ? this.insert(t) : r.replace(t, e)), this;
  }
  splice(t, e, r = "next") {
    const i = r === this.direction ? e : Array.from(e).reverse();
    let o = this;
    const s = this.getParentAtCaret(), l = /* @__PURE__ */ new Map();
    for (let a = o.getAdjacentCaret(); a !== null && l.size < t; a = a.getAdjacentCaret()) {
      const c = a.origin.getWritable();
      l.set(c.getKey(), c);
    }
    for (const a of i) {
      if (l.size > 0) {
        const c = o.getNodeAtCaret();
        if (c) {
          if (l.delete(c.getKey()), l.delete(a.getKey()), !(c.is(a) || o.origin.is(a))) {
            const u = a.getParent();
            u && u.is(s) && a.remove(), c.replace(a);
          }
        } else c === null && b(263, Array.from(l).join(" "));
      } else o.insert(a);
      o = G(a, this.direction);
    }
    for (const a of l.values()) a.remove();
    return this;
  }
}
class Dr extends Vs {
  type = "child";
  getLatest() {
    const t = this.origin.getLatest();
    return t === this.origin ? this : Ut(t, this.direction);
  }
  getParentCaret(t = "root") {
    return G(Gs(this.getParentAtCaret(), t), this.direction);
  }
  getFlipped() {
    const t = Be(this.direction);
    return G(this.getNodeAtCaret(), t) || Ut(this.origin, t);
  }
  getParentAtCaret() {
    return this.origin;
  }
  getChildCaret() {
    return this;
  }
  isSameNodeCaret(t) {
    return t instanceof Dr && this.direction === t.direction && this.origin.is(t.origin);
  }
  isSamePointCaret(t) {
    return this.isSameNodeCaret(t);
  }
}
const _g = { root: ut, shadowRoot: ot };
function Be(n) {
  return pg[n];
}
function Gs(n, t = "root") {
  return n === null || _g[t](n) ? null : Tt(n) === null ? n : null;
}
class on extends Vs {
  type = "sibling";
  getLatest() {
    const t = this.origin.getLatest();
    return t === this.origin ? this : G(t, this.direction);
  }
  getSiblingCaret() {
    return this;
  }
  getParentAtCaret() {
    return this.origin.getParent();
  }
  getChildCaret() {
    return S(this.origin) ? Ut(this.origin, this.direction) : null;
  }
  getParentCaret(t = "root") {
    return G(Gs(this.getParentAtCaret(), t), this.direction);
  }
  getFlipped() {
    const t = Be(this.direction);
    return G(this.getNodeAtCaret(), t) || Ut(this.origin.getParentOrThrow(), t);
  }
  isSamePointCaret(t) {
    return t instanceof on && this.direction === t.direction && this.origin.is(t.origin);
  }
  isSameNodeCaret(t) {
    return (t instanceof on || t instanceof sn) && this.direction === t.direction && this.origin.is(t.origin);
  }
}
class sn extends Vs {
  type = "text";
  offset;
  constructor(t, e) {
    super(t), this.offset = e;
  }
  getLatest() {
    const t = this.origin.getLatest();
    return t === this.origin ? this : Re(t, this.direction, this.offset);
  }
  getParentAtCaret() {
    return this.origin.getParent();
  }
  getChildCaret() {
    return null;
  }
  getParentCaret(t = "root") {
    return G(Gs(this.getParentAtCaret(), t), this.direction);
  }
  getFlipped() {
    return Re(this.origin, Be(this.direction), this.offset);
  }
  isSamePointCaret(t) {
    return t instanceof sn && this.direction === t.direction && this.origin.is(t.origin) && this.offset === t.offset;
  }
  isSameNodeCaret(t) {
    return (t instanceof on || t instanceof sn) && this.direction === t.direction && this.origin.is(t.origin);
  }
  getSiblingCaret() {
    return G(this.origin, this.direction);
  }
}
function Yt(n) {
  return n instanceof sn;
}
function Te(n) {
  return n instanceof on;
}
function Zt(n) {
  return n instanceof Dr;
}
const mg = { next: class extends sn {
  direction = "next";
  getNodeAtCaret() {
    return this.origin.getNextSibling();
  }
  insert(n) {
    return this.origin.insertAfter(n), this;
  }
}, previous: class extends sn {
  direction = "previous";
  getNodeAtCaret() {
    return this.origin.getPreviousSibling();
  }
  insert(n) {
    return this.origin.insertBefore(n), this;
  }
} }, yg = { next: class extends on {
  direction = "next";
  getNodeAtCaret() {
    return this.origin.getNextSibling();
  }
  insert(n) {
    return this.origin.insertAfter(n), this;
  }
}, previous: class extends on {
  direction = "previous";
  getNodeAtCaret() {
    return this.origin.getPreviousSibling();
  }
  insert(n) {
    return this.origin.insertBefore(n), this;
  }
} }, xg = { next: class extends Dr {
  direction = "next";
  getNodeAtCaret() {
    return this.origin.getFirstChild();
  }
  insert(n) {
    return this.origin.splice(0, 0, [n]), this;
  }
}, previous: class extends Dr {
  direction = "previous";
  getNodeAtCaret() {
    return this.origin.getLastChild();
  }
  insert(n) {
    return this.origin.splice(this.origin.getChildrenSize(), 0, [n]), this;
  }
} };
function G(n, t) {
  return n ? new yg[t](n) : null;
}
function Re(n, t, e) {
  return n ? new mg[t](n, ae(n, e)) : null;
}
function ae(n, t, e = "error") {
  const r = n.getTextContentSize();
  let i = t === "next" ? r : t === "previous" ? 0 : t;
  return (i < 0 || i > r) && (e !== "clamp" && Tc(284, String(t), String(r), n.getKey()), i = i < 0 ? 0 : r), i;
}
function va(n, t) {
  return new Cg(n, t);
}
function Ut(n, t) {
  return S(n) ? new xg[t](n) : null;
}
function Sg(n) {
  return n && n.getChildCaret() || n;
}
function Xs(n) {
  return n && Sg(n.getAdjacentCaret());
}
class Ys {
  type = "node-caret-range";
  direction;
  anchor;
  focus;
  constructor(t, e, r) {
    this.anchor = t, this.focus = e, this.direction = r;
  }
  getLatest() {
    const t = this.anchor.getLatest(), e = this.focus.getLatest();
    return t === this.anchor && e === this.focus ? this : new Ys(t, e, this.direction);
  }
  isCollapsed() {
    return this.anchor.isSamePointCaret(this.focus);
  }
  getTextSlices() {
    const t = (i) => {
      const o = this[i].getLatest();
      return Yt(o) ? (function(s, l) {
        const { direction: a, origin: c } = s, u = ae(c, l === "focus" ? Be(a) : a);
        return va(s, u - s.offset);
      })(o, i) : null;
    }, e = t("anchor"), r = t("focus");
    if (e && r) {
      const { caret: i } = e, { caret: o } = r;
      if (i.isSameNodeCaret(o)) return [va(i, o.offset - i.offset), null];
    }
    return [e, r];
  }
  iterNodeCarets(t = "root") {
    const e = Yt(this.anchor) ? this.anchor.getSiblingCaret() : this.anchor.getLatest(), r = this.focus.getLatest(), i = Yt(r), o = (s) => s.isSameNodeCaret(r) ? null : Xs(s) || s.getParentCaret(t);
    return Zs({ hasNext: (s) => s !== null && !(i && r.isSameNodeCaret(s)), initial: e.isSameNodeCaret(r) ? null : o(e), map: (s) => s, step: o });
  }
  [Symbol.iterator]() {
    return this.iterNodeCarets("root");
  }
}
class Cg {
  type = "slice";
  caret;
  distance;
  constructor(t, e) {
    this.caret = t, this.distance = e;
  }
  getSliceIndices() {
    const { distance: t, caret: { offset: e } } = this, r = e + t;
    return r < e ? [r, e] : [e, r];
  }
  getTextContent() {
    const [t, e] = this.getSliceIndices();
    return this.caret.origin.getTextContent().slice(t, e);
  }
  getTextContentSize() {
    return Math.abs(this.distance);
  }
  removeTextSlice() {
    const { caret: { origin: t, direction: e } } = this, [r, i] = this.getSliceIndices(), o = t.getTextContent();
    return Re(t.setTextContent(o.slice(0, r) + o.slice(i)), e, r);
  }
}
function so(n) {
  return oe(n, G(pt(), n.direction));
}
function lo(n) {
  return oe(n, n);
}
function oe(n, t) {
  return n.direction !== t.direction && b(265), new Ys(n, t, n.direction);
}
function Zs(n) {
  const { initial: t, hasNext: e, step: r, map: i } = n;
  let o = t;
  return { [Symbol.iterator]() {
    return this;
  }, next() {
    if (!e(o)) return { done: !0, value: void 0 };
    const s = { done: !1, value: i(o) };
    return o = r(o), s;
  } };
}
function Lr(n, t) {
  const e = fs(n.origin, t.origin);
  switch (e === null && b(275, n.origin.getKey(), t.origin.getKey()), e.type) {
    case "same": {
      const r = n.type === "text", i = t.type === "text";
      return r && i ? (function(o, s) {
        return Math.sign(o - s);
      })(n.offset, t.offset) : n.type === t.type ? 0 : r ? -1 : i ? 1 : n.type === "child" ? -1 : 1;
    }
    case "ancestor":
      return n.type === "child" ? -1 : 1;
    case "descendant":
      return t.type === "child" ? 1 : -1;
    case "branch":
      return vf(e);
  }
}
function vf(n) {
  const { a: t, b: e } = n, r = t.__key, i = e.__key;
  let o = t, s = e;
  for (; o && s; o = o.getNextSibling(), s = s.getNextSibling()) {
    if (o.__key === i) return -1;
    if (s.__key === r) return 1;
  }
  return o === null ? 1 : -1;
}
function ei(n, t) {
  return t.is(n);
}
function ba(n) {
  return S(n) ? [n.getLatest(), null] : [n.getParent(), n.getLatest()];
}
function fs(n, t) {
  if (n.is(t)) return { commonAncestor: n, type: "same" };
  const e = /* @__PURE__ */ new Map();
  for (let [r, i] = ba(n); r; i = r, r = r.getParent()) e.set(r, i);
  for (let [r, i] = ba(t); r; i = r, r = r.getParent()) {
    const o = e.get(r);
    if (o !== void 0) return o === null ? (ei(n, r) || b(276), { commonAncestor: r, type: "ancestor" }) : i === null ? (ei(t, r) || b(277), { commonAncestor: r, type: "descendant" }) : ((S(o) || ei(n, o)) && (S(i) || ei(t, i)) && r.is(o.getParent()) && r.is(i.getParent()) || b(278), { a: o, b: i, commonAncestor: r, type: "branch" });
  }
  return null;
}
function Ft(n, t) {
  const { type: e, key: r, offset: i } = n, o = Vh(n.key);
  return e === "text" ? (O(o) || b(266, o.getType(), r), Re(o, t, i)) : (S(o) || b(267, o.getType(), r), hs(o, n.offset, t));
}
function ln(n, t) {
  const { origin: e, direction: r } = t, i = r === "next";
  Yt(t) ? n.set(e.getKey(), t.offset, "text") : Te(t) ? O(e) ? n.set(e.getKey(), ae(e, r), "text") : n.set(e.getParentOrThrow().getKey(), e.getIndexWithinParent() + (i ? 1 : 0), "element") : (Zt(t) && S(e) || b(268), n.set(e.getKey(), i ? 0 : e.getChildrenSize(), "element"));
}
function $r(n) {
  const t = M(), e = w(t) ? t : zu();
  return yi(e, n), kt(e), e;
}
function yi(n, t) {
  ln(n.anchor, t.anchor), ln(n.focus, t.focus);
}
function ds(n) {
  const { anchor: t, focus: e } = n, r = Ft(t, "next"), i = Ft(e, "next"), o = Lr(r, i) <= 0 ? "next" : "previous";
  return oe(we(r, o), we(i, o));
}
function gn(n) {
  const { direction: t, origin: e } = n, r = G(e, Be(t)).getNodeAtCaret();
  return r ? G(r, t) : Ut(e.getParentOrThrow(), t);
}
function Ta(n, t = "root") {
  const e = [n];
  for (let r = Zt(n) ? n.getParentCaret(t) : n.getSiblingCaret(); r !== null; r = r.getParentCaret(t)) e.push(gn(r));
  return e;
}
function No(n) {
  return !!n && n.origin.isAttached();
}
function vg(n, t = "removeEmptySlices") {
  if (n.isCollapsed()) return n;
  const e = "root", r = "next";
  let i = t;
  const o = tl(n, r), s = Ta(o.anchor, e), l = Ta(o.focus.getFlipped(), e), a = /* @__PURE__ */ new Set(), c = [];
  for (const _ of o.iterNodeCarets(e)) if (Zt(_)) a.add(_.origin.getKey());
  else if (Te(_)) {
    const { origin: m } = _;
    S(m) && !a.has(m.getKey()) || c.push(m);
  }
  for (const _ of c) _.remove();
  for (const _ of o.getTextSlices()) {
    if (!_) continue;
    const { origin: m } = _.caret, p = m.getTextContentSize(), g = gn(G(m, r)), y = m.getMode();
    if (Math.abs(_.distance) === p && i === "removeEmptySlices" || y === "token" && _.distance !== 0) g.remove();
    else if (_.distance !== 0) {
      i = "removeEmptySlices";
      let x = _.removeTextSlice();
      const v = _.caret.origin;
      if (y === "segmented") {
        const E = x.origin, k = mt(E.getTextContent()).setStyle(E.getStyle()).setFormat(E.getFormat());
        g.replaceOrInsert(k), x = Re(k, r, x.offset);
      }
      v.is(s[0].origin) && (s[0] = x), v.is(l[0].origin) && (l[0] = x.getFlipped());
    }
  }
  let u, f;
  for (const _ of s) if (No(_)) {
    u = Wt(_);
    break;
  }
  for (const _ of l) if (No(_)) {
    f = Wt(_);
    break;
  }
  const d = (function(_, m, p) {
    if (!_ || !m) return null;
    const g = _.getParentAtCaret(), y = m.getParentAtCaret();
    if (!g || !y) return null;
    const x = g.getParents().reverse();
    x.push(g);
    const v = y.getParents().reverse();
    v.push(y);
    const E = Math.min(x.length, v.length);
    let k;
    for (k = 0; k < E && x[k] === v[k]; k++) ;
    const N = (A, D) => {
      let I;
      for (let B = k; B < A.length; B++) {
        const P = A[B];
        if (ot(P)) return;
        !I && D(P) && (I = P);
      }
      return I;
    }, C = N(x, bt), T = C && N(v, (A) => p.has(A.getKey()) && bt(A));
    return T && Xt(T).length > 0 ? null : C && T ? [C, T] : null;
  })(u, f, a);
  if (d) {
    const [_, m] = d;
    Ut(_, "previous").splice(0, m.getChildren());
    let p = m.getParent();
    for (m.remove(!0); p && p.isEmpty(); ) {
      const g = p;
      p = p.getParent(), g.remove(!0);
    }
  } else if (f) {
    const _ = (function(g) {
      if (Zt(g)) {
        const y = g.origin;
        if (bt(y)) return y;
      } else {
        const y = g.getParentAtCaret();
        if (y && bt(y)) return y;
      }
      return null;
    })(f), m = _ && _.getParent(), p = _ && _.getParents().findLast(Fn);
    if (_ && m && !ut(m) && _.isEmpty() && a.has(_.getKey()) && Xt(_).length === 0 && (!p || a.has(p.getKey()))) {
      _.remove(!0);
      let g = m;
      for (; g && !ut(g) && g.isEmpty(); ) {
        const y = g.getParent();
        if (y && ut(y) && y.getChildrenSize() <= 1) break;
        const x = g;
        g = y, x.remove(!0);
      }
    }
  }
  const h = [u, f, ...s, ...l].find(No);
  if (h)
    return lo(we(Wt(h), n.direction));
  b(269, JSON.stringify(s.map((_) => _.origin.__key)));
}
function Wt(n) {
  const t = (function(i) {
    let o = i;
    for (; Zt(o); ) {
      const s = Xs(o);
      if (!Zt(s)) break;
      o = s;
    }
    return o;
  })(n.getLatest()), { direction: e } = t;
  if (O(t.origin)) return Yt(t) ? t : Re(t.origin, e, e);
  const r = t.getAdjacentCaret();
  return Te(r) && O(r.origin) ? Re(r.origin, e, Be(e)) : t;
}
function Qs(n) {
  return Yt(n) && n.offset !== ae(n.origin, n.direction);
}
function we(n, t) {
  return n.direction === t ? n : n.getFlipped();
}
function tl(n, t) {
  return n.direction === t ? n : oe(we(n.focus, t), we(n.anchor, t));
}
function hs(n, t, e) {
  let r = Ut(n, "next");
  for (let i = 0; i < t; i++) {
    const o = r.getAdjacentCaret();
    if (o === null) break;
    r = o;
  }
  return we(r, e);
}
function bg(n) {
  const { origin: t, offset: e, direction: r } = n;
  if (e === ae(t, r)) return n.getSiblingCaret();
  if (e === ae(t, Be(r))) return gn(n.getSiblingCaret());
  const [i] = t.splitText(e);
  return O(i) || b(281), we(G(i, "next"), r);
}
function Tg(n, t) {
  return !0;
}
function bf(n, { $copyElementNode: t = At, $splitTextPointCaretNext: e = bg, rootMode: r = "shadowRoot", $shouldSplit: i = Tg, removeEmptyDestination: o = !1 } = {}) {
  if (Yt(n)) return e(n);
  const s = n.getParentCaret(r);
  if (s) {
    const { origin: l } = s;
    if (Zt(n)) {
      const c = gn(s);
      if (o && l.isEmpty()) return l.remove(), c;
      if (!l.canBeEmpty() || !i(l, "first")) return c;
    }
    const a = (function(c) {
      const u = [];
      for (let f = c.getAdjacentCaret(); f; f = f.getAdjacentCaret()) u.push(f.origin);
      return u;
    })(n);
    (a.length > 0 || !o && l.canBeEmpty() && i(l, "last")) && s.insert(t(l).splice(0, 0, a));
  }
  return s;
}
function Tf(n, t, e) {
  let r = we(t, "next");
  Yt(r) && (r.offset === 0 ? r = G(r.origin, "previous").getFlipped() : r.offset === r.origin.getTextContentSize() && (r = G(r.origin, "next"))), r.origin.is(n) && (Te(r) || b(342, n.getKey(), n.getType()), r = gn(r)), (n.is(r.getNodeAtCaret()) || n.is(r.getFlipped().getNodeAtCaret())) && n.remove(!0);
  for (let i = r; i; i = bf(i, e)) r = i;
  return Yt(r) && b(283), r.insert(n.isInline() ? U().append(n) : n), we(G(n.getLatest(), "next"), t.direction);
}
function el(n, t) {
  if (!t || n === t) return n;
  for (const e in t) if (n[e] !== t[e]) return { ...n, ...t };
  return n;
}
function Ce(...n) {
  const t = [];
  for (const e of n) if (e && typeof e == "string") for (const [r] of e.matchAll(/\S+/g)) t.push(r);
  return t;
}
function Lt(n, ...t) {
  const e = Ce(...t);
  e.length > 0 && n.classList.add(...e);
}
function Pn(n, ...t) {
  const e = Ce(...t);
  e.length > 0 && n.classList.remove(...e);
}
function an(...n) {
  return () => {
    for (let t = n.length - 1; t >= 0; t--) n[t]();
    n.length = 0;
  };
}
function wf(n) {
  const t = st().getElementByKey(n.getKey());
  if (t === null) return null;
  const e = t.ownerDocument.defaultView;
  return e === null ? null : e.getComputedStyle(t);
}
function kf(n) {
  return wf(ut(n) ? n : n.getParentOrThrow());
}
function ni(n) {
  const t = kf(n);
  return t !== null && t.direction === "rtl";
}
function Nf(n, t, e = "self") {
  const r = n.getStartEndPoints();
  if (t.isSelected(n) && !zt(t) && r !== null) {
    const [i, o] = r, s = n.isBackward(), l = i.getNode(), a = o.getNode(), c = t.is(l), u = t.is(a);
    if (c || u) {
      const [f, d] = rs(n), h = l.is(a), _ = t.is(s ? a : l), m = t.is(s ? l : a);
      let p, g = 0;
      h ? (g = f > d ? d : f, p = f > d ? f : d) : _ ? (g = s ? d : f, p = void 0) : m && (g = 0, p = s ? f : d);
      const y = t.__text.slice(g, p);
      y !== t.__text && (e === "clone" && (t = sg(t)), t.__text = y);
    }
  }
  return t;
}
function wg(n, t) {
  const e = n.getFormatType(), r = n.getIndent();
  e !== t.getFormatType() && t.setFormat(e), r !== t.getIndent() && t.setIndent(r);
}
function kg(n, t, e) {
  let r = Ft(n, e);
  if (Qs(r)) return !1;
  for (; r; r = r.getParentCaret()) {
    const i = r.getParentAtCaret();
    if (!i || r.getNodeAtCaret()) return !1;
    if (t.is(i)) return !0;
  }
  return !1;
}
function Eo(n, t, e = wg) {
  if (!n) return;
  const r = n.getStartEndPoints();
  let i = !1, o = null;
  const s = /* @__PURE__ */ new Map();
  if (r) {
    const [l, a] = r, c = dt(l.getNode(), bt);
    o = dt(a.getNode(), bt);
    const u = n.isBackward() ? "previous" : "next";
    i = S(o) && !o.is(c) && (function(f, d, h) {
      const _ = f.getNode();
      return (!S(_) || !_.isEmpty()) && kg(f, d, h);
    })(a, o, Be(u)), S(c) && s.set(c.getKey(), c), S(o) && !i && s.set(o.getKey(), o);
  }
  for (const l of n.getNodes()) if (S(l) && bt(l)) {
    if (i && l.is(o)) continue;
    s.set(l.getKey(), l);
  } else if (!r) {
    const a = dt(l, bt);
    S(a) && s.set(a.getKey(), a);
  }
  for (const l of s.values()) {
    const a = t();
    e(l, a), l.replace(a, !0);
  }
}
function Ef(n) {
  const t = Of(n);
  return t !== null && t.writingMode === "vertical-rl";
}
function Of(n) {
  const t = n.anchor.getNode();
  return S(t) ? wf(t) : kf(t);
}
function wa(n, t) {
  let e = Ef(n) ? !t : t;
  Af(n) && (e = !e);
  const r = Ft(n.focus, e ? "previous" : "next");
  if (Qs(r)) return !1;
  if (Yt(r) && !Ar(r.origin) && r.origin.isUnmergeable()) {
    const i = r.getNodeAtCaret();
    if (O(i) && !Ar(i)) return !0;
  }
  for (const i of so(r)) {
    if (Zt(i)) return !i.origin.isInline();
    if (!S(i.origin)) {
      if (W(i.origin)) return !0;
      break;
    }
  }
  return !1;
}
function Ng(n, t, e, r) {
  n.modify(t ? "extend" : "move", e, r);
}
function Af(n) {
  const t = Of(n);
  return t !== null && t.direction === "rtl";
}
function ka(n, t, e) {
  const r = Af(n);
  let i;
  i = Ef(n) || r ? !e : e, Ng(n, t, i, "character");
}
function Eg(n, ...t) {
  const e = new URL("https://lexical.dev/docs/error"), r = new URLSearchParams();
  r.append("code", n);
  for (const i of t) r.append("v", i);
  throw e.search = r.toString(), Error(`Minified Lexical error #${n}; visit ${e.toString()} for the full message or use the non-minified dev environment for full errors and additional helpful warnings.`);
}
function Og(n, t) {
  let e = n;
  for (; e != null; ) {
    if (e instanceof t) return e;
    e = e.getParent();
  }
  return null;
}
function Ag(n) {
  const t = dt(n, (e) => S(e) && !e.isInline());
  return S(t) || Eg(4, n.__key), t;
}
function Me(n, t) {
  return n !== null && Object.getPrototypeOf(n).constructor.name === t.name;
}
function ri(n) {
  let t = null;
  if (Me(n, DragEvent) ? t = n.dataTransfer : Me(n, ClipboardEvent) && (t = n.clipboardData), t === null) return [!1, [], !1];
  const e = t.types, r = e.includes("Files"), i = e.includes("text/html") || e.includes("text/plain");
  return [r, Array.from(t.files), i];
}
function Na(n) {
  const t = M();
  if (!w(t)) return !1;
  const e = /* @__PURE__ */ new Set(), r = t.getNodes();
  for (let i = 0; i < r.length; i++) {
    const o = r[i], s = o.getKey();
    if (e.has(s)) continue;
    const l = dt(o, (c) => S(c) && !c.isInline());
    if (l === null) continue;
    const a = l.getKey();
    l.canIndent() && !e.has(a) && (e.add(a), n(l));
  }
  return e.size > 0;
}
function Bi(n, t) {
  const e = [], r = Array.from(n).reverse();
  for (let i = r.pop(); i !== void 0; i = r.pop()) if (t(i)) e.push(i);
  else if (S(i)) for (const o of Mg(i)) r.push(o);
  return e;
}
function Mg(n) {
  return Dg(Ut(n, "previous"));
}
function Dg(n) {
  return Zs({ hasNext: Te, initial: n.getAdjacentCaret(), map: (t) => t.origin.getLatest(), step: (t) => t.getAdjacentCaret() });
}
const Lg = /* @__PURE__ */ Symbol.for("preact-signals");
function nl() {
  if (Ye > 1) return void Ye--;
  let n, t = !1;
  for (!(function() {
    let e = Ui;
    for (Ui = void 0; e !== void 0; ) e.S.v === e.v && (e.S.i = e.i), e = e.o;
  })(); yr !== void 0; ) {
    let e = yr;
    for (yr = void 0, Hi++; e !== void 0; ) {
      const r = e.u;
      if (e.u = void 0, e.f &= -3, !(8 & e.f) && Mf(e)) try {
        e.c();
      } catch (i) {
        t || (n = i, t = !0);
      }
      e = r;
    }
  }
  if (Hi = 0, Ye--, t) throw n;
}
let X, yr;
function Ea(n) {
  const t = X;
  X = void 0;
  try {
    return n();
  } finally {
    X = t;
  }
}
let Ui, Ye = 0, Hi = 0, Oa = 0, xi = 0;
function Aa(n) {
  if (X === void 0) return;
  let t = n.n;
  return t === void 0 || t.t !== X ? (t = { i: 0, S: n, p: X.s, n: void 0, t: X, e: void 0, x: void 0, r: t }, X.s !== void 0 && (X.s.n = t), X.s = t, n.n = t, 32 & X.f && n.S(t), t) : t.i === -1 ? (t.i = 0, t.n !== void 0 && (t.n.p = t.p, t.p !== void 0 && (t.p.n = t.n), t.p = X.s, t.n = void 0, X.s.n = t, X.s = t), t) : void 0;
}
function Et(n, t) {
  this.v = n, this.i = 0, this.n = void 0, this.t = void 0, this.l = 0, this.W = t?.watched, this.Z = t?.unwatched, this.name = t?.name;
}
function $g(n, t) {
  return new Et(n, t);
}
function Mf(n) {
  for (let t = n.s; t !== void 0; t = t.n) if (t.S.i !== t.i || !t.S.h() || t.S.i !== t.i) return !0;
  return !1;
}
function Ma(n) {
  for (let t = n.s; t !== void 0; t = t.n) {
    const e = t.S.n;
    if (e !== void 0 && (t.r = e), t.S.n = t, t.i = -1, t.n === void 0) {
      n.s = t;
      break;
    }
  }
}
function Df(n) {
  let t, e = n.s;
  for (; e !== void 0; ) {
    const r = e.p;
    e.i === -1 ? (e.S.U(e), r !== void 0 && (r.n = e.n), e.n !== void 0 && (e.n.p = r)) : t = e, e.S.n = e.r, e.r !== void 0 && (e.r = void 0), e = r;
  }
  n.s = t;
}
function yn(n, t) {
  Et.call(this, void 0), this.x = n, this.s = void 0, this.g = xi - 1, this.f = 4, this.W = t?.watched, this.Z = t?.unwatched, this.name = t?.name;
}
function Lf(n) {
  const t = n.m;
  if (n.m = void 0, typeof t == "function") {
    Ye++;
    const e = X;
    X = void 0;
    try {
      t();
    } catch (r) {
      throw n.f &= -2, n.f |= 8, rl(n), r;
    } finally {
      X = e, nl();
    }
  }
}
function rl(n) {
  for (let t = n.s; t !== void 0; t = t.n) t.S.U(t);
  n.x = void 0, n.s = void 0, Lf(n);
}
function Fg(n) {
  if (X !== this) throw new Error("Out-of-order effect");
  Df(this), X = n, this.f &= -2, 8 & this.f && rl(this), nl();
}
function Tn(n, t) {
  this.x = n, this.m = void 0, this.s = void 0, this.u = void 0, this.f = 32, this.name = t?.name;
}
function Pg(n, t) {
  const e = new Tn(n, t);
  try {
    e.c();
  } catch (i) {
    throw e.d(), i;
  }
  const r = e.d.bind(e);
  return r[Symbol.dispose] = r, r;
}
Et.prototype.brand = Lg, Et.prototype.h = function() {
  return !0;
}, Et.prototype.S = function(n) {
  const t = this.t;
  t !== n && n.e === void 0 && (n.x = t, this.t = n, t !== void 0 ? t.e = n : Ea(() => {
    var e;
    (e = this.W) == null || e.call(this);
  }));
}, Et.prototype.U = function(n) {
  if (this.t !== void 0) {
    const t = n.e, e = n.x;
    t !== void 0 && (t.x = e, n.e = void 0), e !== void 0 && (e.e = t, n.x = void 0), n === this.t && (this.t = e, e === void 0 && Ea(() => {
      var r;
      (r = this.Z) == null || r.call(this);
    }));
  }
}, Et.prototype.subscribe = function(n) {
  return Pg(() => {
    const t = this.value, e = X;
    X = void 0;
    try {
      n(t);
    } finally {
      X = e;
    }
  }, { name: "sub" });
}, Et.prototype.valueOf = function() {
  return this.value;
}, Et.prototype.toString = function() {
  return this.value + "";
}, Et.prototype.toJSON = function() {
  return this.value;
}, Et.prototype.peek = function() {
  const n = X;
  X = void 0;
  try {
    return this.value;
  } finally {
    X = n;
  }
}, Object.defineProperty(Et.prototype, "value", { get() {
  const n = Aa(this);
  return n !== void 0 && (n.i = this.i), this.v;
}, set(n) {
  if (n !== this.v) {
    if (Hi > 100) throw new Error("Cycle detected");
    (function(t) {
      Ye !== 0 && Hi === 0 && t.l !== Oa && (t.l = Oa, Ui = { S: t, v: t.v, i: t.i, o: Ui });
    })(this), this.v = n, this.i++, xi++, Ye++;
    try {
      for (let t = this.t; t !== void 0; t = t.x) t.t.N();
    } finally {
      nl();
    }
  }
} }), yn.prototype = new Et(), yn.prototype.h = function() {
  if (this.f &= -3, 1 & this.f) return !1;
  if ((36 & this.f) == 32 || (this.f &= -5, this.g === xi)) return !0;
  if (this.g = xi, this.f |= 1, this.i > 0 && !Mf(this)) return this.f &= -2, !0;
  const n = X;
  try {
    Ma(this), X = this;
    const t = this.x();
    (16 & this.f || this.v !== t || this.i === 0) && (this.v = t, this.f &= -17, this.i++);
  } catch (t) {
    this.v = t, this.f |= 16, this.i++;
  }
  return X = n, Df(this), this.f &= -2, !0;
}, yn.prototype.S = function(n) {
  if (this.t === void 0) {
    this.f |= 36;
    for (let t = this.s; t !== void 0; t = t.n) t.S.S(t);
  }
  Et.prototype.S.call(this, n);
}, yn.prototype.U = function(n) {
  if (this.t !== void 0 && (Et.prototype.U.call(this, n), this.t === void 0)) {
    this.f &= -33;
    for (let t = this.s; t !== void 0; t = t.n) t.S.U(t);
  }
}, yn.prototype.N = function() {
  if (!(2 & this.f)) {
    this.f |= 6;
    for (let n = this.t; n !== void 0; n = n.x) n.t.N();
  }
}, Object.defineProperty(yn.prototype, "value", { get() {
  if (1 & this.f) throw new Error("Cycle detected");
  const n = Aa(this);
  if (this.h(), n !== void 0 && (n.i = this.i), 16 & this.f) throw this.v;
  return this.v;
} }), Tn.prototype.c = function() {
  const n = this.S();
  try {
    if (8 & this.f || this.x === void 0) return;
    const t = this.x();
    typeof t == "function" && (this.m = t);
  } finally {
    n();
  }
}, Tn.prototype.S = function() {
  if (1 & this.f) throw new Error("Cycle detected");
  this.f |= 1, this.f &= -9, Lf(this), Ma(this), Ye++;
  const n = X;
  return X = this, Fg.bind(this, n);
}, Tn.prototype.N = function() {
  2 & this.f || (this.f |= 2, this.u = yr, yr = this);
}, Tn.prototype.d = function() {
  this.f |= 8, 1 & this.f || rl(this);
}, Tn.prototype.dispose = function() {
  this.d();
};
function Ig(n) {
  return (typeof n.nodes == "function" ? n.nodes() : n.nodes) || [];
}
function Q(n, ...t) {
  const e = new URL("https://lexical.dev/docs/error"), r = new URLSearchParams();
  r.append("code", n);
  for (const i of t) r.append("v", i);
  throw e.search = r.toString(), Error(`Minified Lexical error #${n}; visit ${e.toString()} for the full message or use the non-minified dev environment for full errors and additional helpful warnings.`);
}
let $f;
try {
  $f = "0.48.0+prod.esm";
} catch {
}
const Rg = $f ?? '"<unknown>+source"', zg = /* @__PURE__ */ new Set(["__proto__", "constructor", "prototype"]);
function Ff(n, t) {
  if (n && t && !Array.isArray(t) && typeof n == "object" && typeof t == "object") {
    const e = n, r = t;
    for (const i in r) !zg.has(i) && Object.prototype.hasOwnProperty.call(r, i) && (e[i] = Ff(e[i], r[i]));
    return n;
  }
  return t;
}
const il = 0, gs = 1, Pf = 2, Oo = 3, ii = 4, xn = 5, Ao = 6, fr = 7;
function Mo(n) {
  return n.id === il;
}
function If(n) {
  return n.id === Pf;
}
function Wg(n) {
  return (function(t) {
    return t.id === gs;
  })(n) || Q(305, String(n.id), String(gs)), Object.assign(n, { id: Pf });
}
const Kg = /* @__PURE__ */ new Set();
let Bg = class {
  builder;
  configs;
  _dependency;
  _peerNameSet;
  extension;
  state;
  _signal;
  constructor(t, e) {
    this.builder = t, this.extension = e, this.configs = /* @__PURE__ */ new Set(), this.state = { id: il };
  }
  mergeConfigs() {
    let t = this.extension.config || {};
    const e = this.extension.mergeConfig ? this.extension.mergeConfig.bind(this.extension) : el;
    for (const r of this.configs) t = e(t, r);
    return t;
  }
  init(t) {
    const e = this.state;
    If(e) || Q(306, String(e.id));
    const r = { getDependency: this.getInitDependency.bind(this), getDirectDependentNames: this.getDirectDependentNames.bind(this), getPeer: this.getInitPeer.bind(this), getPeerNameSet: this.getPeerNameSet.bind(this) }, i = { ...r, getDependency: this.getDependency.bind(this), getInitResult: this.getInitResult.bind(this), getPeer: this.getPeer.bind(this) }, o = (function(l, a, c) {
      return Object.assign(l, { config: a, id: Oo, registerState: c });
    })(e, this.mergeConfigs(), r);
    let s;
    this.state = o, this.extension.init && (s = this.extension.init(t, o.config, r)), this.state = (function(l, a, c) {
      return Object.assign(l, { id: ii, initResult: a, registerState: c });
    })(o, s, i);
  }
  build(t) {
    const e = this.state;
    let r;
    e.id !== ii && Q(307, String(e.id), String(xn)), this.extension.build && (r = this.extension.build(t, e.config, e.registerState));
    const i = { ...e.registerState, getOutput: () => r, getSignal: this.getSignal.bind(this) };
    this.state = (function(o, s, l) {
      return Object.assign(o, { id: xn, output: s, registerState: l });
    })(e, r, i);
  }
  register(t, e) {
    this._signal = e;
    const r = this.state;
    r.id !== xn && Q(308, String(r.id), String(xn));
    const i = this.extension.register && this.extension.register(t, r.config, r.registerState);
    return this.state = (function(o) {
      return Object.assign(o, { id: Ao });
    })(r), () => {
      const o = this.state;
      o.id !== fr && Q(309, String(r.id), String(fr)), this.state = (function(s) {
        return Object.assign(s, { id: xn });
      })(o), i && i();
    };
  }
  afterRegistration(t) {
    const e = this.state;
    let r;
    return e.id !== Ao && Q(310, String(e.id), String(Ao)), this.extension.afterRegistration && (r = this.extension.afterRegistration(t, e.config, e.registerState)), this.state = (function(i) {
      return Object.assign(i, { id: fr });
    })(e), r;
  }
  getSignal() {
    return this._signal === void 0 && Q(311), this._signal;
  }
  getInitResult() {
    this.extension.init === void 0 && Q(312, this.extension.name);
    const t = this.state;
    return (function(e) {
      return e.id >= ii;
    })(t) || Q(313, String(t.id), String(ii)), t.initResult;
  }
  getInitPeer(t) {
    const e = this.builder.extensionNameMap.get(t);
    return e ? e.getExtensionInitDependency() : void 0;
  }
  getExtensionInitDependency() {
    const t = this.state;
    return (function(e) {
      return e.id >= Oo;
    })(t) || Q(314, String(t.id), String(Oo)), { config: t.config };
  }
  getPeer(t) {
    const e = this.builder.extensionNameMap.get(t);
    return e ? e.getExtensionDependency() : void 0;
  }
  getInitDependency(t) {
    const e = this.builder.getExtensionRep(t);
    return e === void 0 && Q(315, this.extension.name, t.name), e.getExtensionInitDependency();
  }
  getDependency(t) {
    const e = this.builder.getExtensionRep(t);
    return e === void 0 && Q(315, this.extension.name, t.name), e.getExtensionDependency();
  }
  getState() {
    const t = this.state;
    return (function(e) {
      return e.id >= fr;
    })(t) || Q(316, String(t.id), String(fr)), t;
  }
  getDirectDependentNames() {
    return this.builder.incomingEdges.get(this.extension.name) || Kg;
  }
  getPeerNameSet() {
    let t = this._peerNameSet;
    return t || (t = new Set((this.extension.peerDependencies || []).map(([e]) => e)), this._peerNameSet = t), t;
  }
  getExtensionDependency() {
    if (!this._dependency) {
      const t = this.state;
      (function(e) {
        return e.id >= xn;
      })(t) || Q(317, this.extension.name), this._dependency = { config: t.config, init: t.initResult, output: t.output };
    }
    return this._dependency;
  }
};
const Da = { tag: _r };
function Ug() {
  const n = pt();
  n.isEmpty() && n.append(U());
}
const Hg = { config: { setOptions: Da, updateOptions: Da }, init: ({ $initialEditorState: n = Ug }) => ({ $initialEditorState: n, initialized: !1 }), afterRegistration(n, { updateOptions: t, setOptions: e }, r) {
  const i = r.getInitResult();
  if (!i.initialized) {
    i.initialized = !0;
    const { $initialEditorState: o } = i;
    if (Lh(o)) n.setEditorState(o, e);
    else if (typeof o == "function") n.update(() => {
      o(n);
    }, t);
    else if (o && (typeof o == "string" || typeof o == "object")) {
      const s = n.parseEditorState(o);
      n.setEditorState(s, e);
    }
  }
  return () => {
  };
}, name: "@lexical/extension/InitialState", nodes: [Zn, We, Qn, Gn, tr] }, La = /* @__PURE__ */ Symbol.for("@lexical/extension/LexicalBuilder");
function $a() {
}
function Jg(n) {
  throw n;
}
function oi(n) {
  return Array.isArray(n) ? n : [n];
}
const Do = Rg;
class xr {
  roots;
  extensionNameMap;
  outgoingConfigEdges;
  incomingEdges;
  conflicts;
  _sortedExtensionReps;
  PACKAGE_VERSION;
  constructor(t) {
    this.outgoingConfigEdges = /* @__PURE__ */ new Map(), this.incomingEdges = /* @__PURE__ */ new Map(), this.extensionNameMap = /* @__PURE__ */ new Map(), this.conflicts = /* @__PURE__ */ new Map(), this.PACKAGE_VERSION = Do, this.roots = t;
    for (const e of t) this.addExtension(e);
  }
  static fromExtensions(t) {
    const e = [oi(Hg)];
    for (const r of t) e.push(oi(r));
    return new xr(e);
  }
  static maybeFromEditor(t) {
    const e = t[La];
    return e && (e.PACKAGE_VERSION !== Do && Q(292, e.PACKAGE_VERSION, Do), e instanceof xr || Q(293)), e;
  }
  static fromEditor(t) {
    const e = xr.maybeFromEditor(t);
    return e === void 0 && Q(294), e;
  }
  constructEditor() {
    const { $initialEditorState: t, onError: e, onWarn: r, ...i } = this.buildCreateEditorArgs(), o = Object.assign(Xu({ ...i, ...e ? { onError: (s) => {
      e(s, o);
    } } : {}, ...r ? { onWarn: (s) => {
      r(s, o);
    } } : {} }), { [La]: this });
    for (const s of this.sortedExtensionReps()) s.build(o);
    return o;
  }
  buildEditor() {
    let t = $a;
    function e() {
      try {
        t();
      } finally {
        t = $a;
      }
    }
    const r = Object.assign(this.constructEditor(), { dispose: e, [Symbol.dispose]: e });
    return t = an(this.registerEditor(r), () => r.setRootElement(null)), r;
  }
  hasExtensionByName(t) {
    return this.extensionNameMap.has(t);
  }
  getExtensionRep(t) {
    const e = this.extensionNameMap.get(t.name);
    if (e) return e.extension !== t && Q(295, t.name), e;
  }
  addEdge(t, e, r) {
    const i = this.outgoingConfigEdges.get(t);
    i ? i.set(e, r) : this.outgoingConfigEdges.set(t, /* @__PURE__ */ new Map([[e, r]]));
    const o = this.incomingEdges.get(e);
    o ? o.add(t) : this.incomingEdges.set(e, /* @__PURE__ */ new Set([t]));
  }
  addExtension(t) {
    this._sortedExtensionReps !== void 0 && Q(296);
    const e = oi(t), [r] = e;
    typeof r.name != "string" && Q(297, typeof r.name);
    let i = this.extensionNameMap.get(r.name);
    if (i !== void 0 && i.extension !== r && Q(298, r.name), !i) {
      i = new Bg(this, r), this.extensionNameMap.set(r.name, i);
      const o = this.conflicts.get(r.name);
      typeof o == "string" && Q(299, r.name, o);
      for (const s of r.conflictsWith || []) this.extensionNameMap.has(s) && Q(299, r.name, s), this.conflicts.set(s, r.name);
      for (const s of r.dependencies || []) {
        const l = oi(s);
        this.addEdge(r.name, l[0].name, l.slice(1)), this.addExtension(l);
      }
      for (const [s, l] of r.peerDependencies || []) this.addEdge(r.name, s, l ? [l] : []);
    }
  }
  sortedExtensionReps() {
    if (this._sortedExtensionReps) return this._sortedExtensionReps;
    const t = [], e = (r, i) => {
      let o = r.state;
      if (If(o)) return;
      const s = r.extension.name;
      var l;
      Mo(o) || Q(300, s, i || "[unknown]"), Mo(l = o) || Q(304, String(l.id), String(il)), o = Object.assign(l, { id: gs }), r.state = o;
      const a = this.outgoingConfigEdges.get(s);
      if (a) for (const c of a.keys()) {
        const u = this.extensionNameMap.get(c);
        u && e(u, s);
      }
      o = Wg(o), r.state = o, t.push(r);
    };
    for (const r of this.extensionNameMap.values()) Mo(r.state) && e(r);
    for (const r of t) for (const [i, o] of this.outgoingConfigEdges.get(r.extension.name) || []) if (o.length > 0) {
      const s = this.extensionNameMap.get(i);
      if (s) for (const l of o) s.configs.add(l);
    }
    for (const [r, ...i] of this.roots) if (i.length > 0) {
      const o = this.extensionNameMap.get(r.name);
      o === void 0 && Q(301, r.name);
      for (const s of i) o.configs.add(s);
    }
    return this._sortedExtensionReps = t, this._sortedExtensionReps;
  }
  registerEditor(t) {
    const e = this.sortedExtensionReps(), r = new AbortController(), i = [() => r.abort()], o = r.signal;
    for (const s of e) {
      const l = s.register(t, o);
      l && i.push(l);
    }
    for (const s of e) {
      const l = s.afterRegistration(t);
      l && i.push(l);
    }
    return an(...i);
  }
  buildCreateEditorArgs() {
    const t = {}, e = /* @__PURE__ */ new Set(), r = /* @__PURE__ */ new Map(), i = /* @__PURE__ */ new Map(), o = {}, s = {}, l = this.sortedExtensionReps();
    for (const u of l) {
      const { extension: f } = u;
      if (f.onError !== void 0 && (t.onError = f.onError), f.onWarn !== void 0 && (t.onWarn = f.onWarn), f.disableEvents !== void 0 && (t.disableEvents = f.disableEvents), f.parentEditor !== void 0 && (t.parentEditor = f.parentEditor), f.editable !== void 0 && (t.editable = f.editable), f.namespace !== void 0 && (t.namespace = f.namespace), f.$initialEditorState !== void 0 && (t.$initialEditorState = f.$initialEditorState), f.nodes) for (const d of Ig(f)) {
        if (typeof d != "function") {
          const h = r.get(d.replace);
          h && Q(302, f.name, d.replace.name, h.extension.name), r.set(d.replace, u);
        }
        e.add(d);
      }
      if (f.html) {
        if (f.html.export) for (const [d, h] of f.html.export.entries()) i.set(d, h);
        f.html.import && Object.assign(o, f.html.import);
      }
      f.theme && Ff(s, f.theme);
    }
    Object.keys(s).length > 0 && (t.theme = s), e.size && (t.nodes = [...e]);
    const a = Object.keys(o).length > 0, c = i.size > 0;
    (a || c) && (t.html = {}, a && (t.html.import = o), c && (t.html.export = i));
    for (const u of l) u.init(t);
    return t.onError || (t.onError = Jg), t;
  }
}
function ao(n, t) {
  const e = xr.maybeFromEditor(n);
  if (!e) return;
  const r = e.extensionNameMap.get(t);
  return r ? r.getExtensionDependency() : void 0;
}
function jg(n) {
  return ao(st(), n);
}
class co extends Yn {
  static getType() {
    return "horizontalrule";
  }
  static clone(t) {
    return new co(t.__key);
  }
  static importJSON(t) {
    return ol().updateFromJSON(t);
  }
  static importDOM() {
    return { hr: () => ({ conversion: qg, priority: 0 }) };
  }
  exportDOM() {
    return { element: V().createElement("hr") };
  }
  createDOM(t) {
    const e = V().createElement("hr");
    return Lt(e, t.theme.hr), e;
  }
  getTextContent() {
    return `
`;
  }
  isInline() {
    return !1;
  }
  updateDOM() {
    return !1;
  }
}
function qg() {
  return { node: ol() };
}
function ol() {
  return ag(co);
}
function Br(n, ...t) {
  const e = new URL("https://lexical.dev/docs/error"), r = new URLSearchParams();
  r.append("code", n);
  for (const i of t) r.append("v", i);
  throw e.search = r.toString(), Error(`Minified Lexical error #${n}; visit ${e.toString()} for the full message or use the non-minified dev environment for full errors and additional helpful warnings.`);
}
let In;
function Rf(n, t) {
  const { key: e } = t;
  return n && e in n ? n[e] : t.defaultValue;
}
function zf(n) {
  return In && In.editor === n ? In : void 0;
}
function Vg(n, t) {
  if ("cfg" in t) {
    const { cfg: e, updater: r } = t;
    return [e, r(Rf(n, e))];
  }
  return t;
}
function Gg(n, t) {
  let e = t;
  for (const r of n) {
    const [i, o] = Vg(e, r), s = i.key;
    if (e === t && Rf(e, i) === o) continue;
    const l = e === t || e === void 0 ? Xg(t) : e;
    l[s] = o, e = l;
  }
  return e;
}
function Xg(n) {
  return Object.create(n || null);
}
function Ji(n, t) {
  return [n, t];
}
function Yg(n, t, e, r = st()) {
  const i = In, o = zf(r);
  try {
    return In = { ...o, editor: r, [n]: t }, e();
  } finally {
    In = i;
  }
}
function Zg(n, t = () => {
}) {
  return (e, r = st()) => (i) => {
    const o = zf(r), s = o && o[n], l = Gg(e, s || t(r));
    return l && l !== s ? Yg(n, l, i, r) : i();
  };
}
function Wf(n, t, e, r) {
  return Object.assign(Fc(Symbol(t), { isEqual: r, parse: e }), { [n]: !0 });
}
function Qg(n) {
  if (!fn(n)) return;
  const t = n;
  if (t.querySelector("style") === null) return;
  const e = /* @__PURE__ */ new Map();
  function r(i) {
    let o = e.get(i);
    if (o === void 0) {
      o = /* @__PURE__ */ new Set();
      for (let s = 0; s < i.style.length; s++) o.add(i.style[s]);
      e.set(i, o);
    }
    return o;
  }
  try {
    for (const i of Array.from(t.styleSheets)) {
      let o;
      try {
        o = i.cssRules;
      } catch {
        continue;
      }
      for (const s of Array.from(o)) {
        if (!Me(s, CSSStyleRule)) continue;
        let l;
        try {
          l = t.querySelectorAll(s.selectorText);
        } catch {
          continue;
        }
        for (const a of Array.from(l)) {
          if (!F(a)) continue;
          const c = r(a);
          for (let u = 0; u < s.style.length; u++) {
            const f = s.style[u];
            c.has(f) || a.style.setProperty(f, s.style.getPropertyValue(f), s.style.getPropertyPriority(f));
          }
        }
      }
    }
  } catch {
  }
}
const Kf = "@lexical/html/DOM", Bf = /* @__PURE__ */ Symbol.for("@lexical/html/DOMExportContext"), tp = /* @__PURE__ */ Symbol.for("@lexical/html/DOMImportContext");
function ep(n, t, e) {
  return Wf(Bf, n, t, e);
}
const np = /* @__PURE__ */ ep("isExport", Boolean);
function rp(n) {
  const t = ao(n, Kf);
  return t ? t.output.defaults : void 0;
}
function ip(n) {
  const t = ao(n, Kf);
  return t ? t.output.runtime : void 0;
}
function op(n = st()) {
  const t = ip(n);
  return t ? t.getSessionConfig() : ro(n);
}
const sp = Zg(Bf, rp), sl = /* @__PURE__ */ Symbol.for("@lexical/html/SelectorImpl");
function Fr(n, t) {
  const e = { kind: "element", predicate: (r = t, r.length === 0 ? F : r.length === 1 ? r[0] : (o, s) => {
    for (const l of r) if (!l(o, s)) return !1;
    return !0;
  }), tags: n };
  var r;
  const i = (o) => Fr(n, [...t, o]);
  return { [sl]: e, attr: (o, s, l) => i(ps(o, s, l)), classAll: (...o) => i(Hf(o)), classAny: (...o) => i((function(s) {
    const l = Uf(s);
    return l.length === 0 ? () => !1 : (a) => {
      if (!F(a)) return !1;
      const c = a.classList;
      for (const u of l) if (c.contains(u)) return !0;
      return !1;
    };
  })(o)), styleAny: (o, s, l) => i((function(a, c, u) {
    if (typeof c == "string") return (f) => F(f) && f.style.getPropertyValue(a) === c;
    if (c instanceof RegExp) {
      const f = u && u.capture, d = c;
      return (h, _) => {
        if (!F(h)) return !1;
        const m = h.style.getPropertyValue(a);
        if (!m) return !1;
        const p = m.match(d);
        return p !== null && (f !== void 0 && (_[f] = p), !0);
      };
    }
    Br(362, JSON.stringify(a));
  })(o, s, l)) };
}
function Uf(n) {
  const t = [];
  for (const e of n) e && t.push(e);
  return t;
}
function Hf(n) {
  const t = Uf(n);
  return t.length === 0 ? () => !0 : (e) => {
    if (!F(e)) return !1;
    const r = e.classList;
    for (const i of t) if (!r.contains(i)) return !1;
    return !0;
  };
}
function ps(n, t, e) {
  if (t === !0) return (r) => F(r) && r.hasAttribute(n);
  if (typeof t == "string") return (r) => F(r) && r.getAttribute(n) === t;
  if (t instanceof RegExp) {
    const r = e && e.capture, i = t;
    return (o, s) => {
      if (!F(o)) return !1;
      const l = o.getAttribute(n);
      if (l == null) return !1;
      const a = l.match(i);
      return a !== null && (r !== void 0 && (s[r] = a), !0);
    };
  }
  Br(361, JSON.stringify(n));
}
const lp = { kind: "text", predicate: Ht, tags: /* @__PURE__ */ new Set() }, ap = { [sl]: lp }, cp = { kind: "comment", predicate: (n) => n.nodeType === 8, tags: /* @__PURE__ */ new Set() }, up = { [sl]: cp }, Nn = { any: () => Fr(/* @__PURE__ */ new Set(), []), comment: () => up, tag(...n) {
  n.length > 0 || Br(363);
  const t = /* @__PURE__ */ new Set();
  for (const e of n) t.add(e.toUpperCase());
  return Fr(t, []);
}, text: () => ap };
function Jf(n, t) {
  return F(n) && n.nodeName === t.toUpperCase();
}
const jf = /[A-Za-z0-9_-]/;
class fp {
  constructor(t, e) {
    this.source = t, this.pos = e;
  }
  peek(t = 0) {
    return this.source[this.pos + t] || "";
  }
  consume() {
    return this.source[this.pos++] || "";
  }
  eof() {
    return this.pos >= this.source.length;
  }
  skipWhitespace() {
    for (; !this.eof() && /\s/.test(this.peek()); ) this.pos++;
  }
  readIdent() {
    const t = this.pos;
    for (; !this.eof() && jf.test(this.peek()); ) this.pos++;
    return this.source.slice(t, this.pos);
  }
  readQuoted() {
    const t = this.consume();
    this.assert(t === '"' || t === "'", "expected quote");
    const e = this.pos;
    for (; !this.eof() && this.peek() !== t; ) this.peek() === "\\" ? this.pos += 2 : this.pos++;
    this.assert(!this.eof(), "unterminated string");
    const r = this.source.slice(e, this.pos);
    return this.pos++, r.replace(/\\(.)/g, "$1");
  }
  assert(t, e) {
    t || Br(364, String(this.pos + 1), e, this.source);
  }
}
function dp(n) {
  const t = /* @__PURE__ */ new Set(), e = [], r = [];
  if (n.skipWhitespace(), n.peek() === "*") n.consume();
  else if (jf.test(n.peek())) {
    const i = n.readIdent();
    i && t.add(i.toUpperCase());
  }
  for (; !n.eof(); ) {
    const i = n.peek();
    if (i === ".") {
      n.consume();
      const o = n.readIdent();
      n.assert(o !== "", 'expected class name after "."'), r.push(o);
    } else if (i === "#") {
      n.consume();
      const o = n.readIdent();
      n.assert(o !== "", 'expected id after "#"'), e.push(ps("id", o));
    } else {
      if (i !== "[") break;
      {
        n.consume(), n.skipWhitespace();
        const o = n.readIdent();
        n.assert(o !== "", 'expected attribute name after "["'), n.skipWhitespace();
        let s = !0;
        if (n.peek() === "=") {
          n.consume(), n.skipWhitespace();
          const l = n.peek();
          l === '"' || l === "'" ? s = n.readQuoted() : (s = n.readIdent(), n.assert(s !== "", "expected attribute value")), n.skipWhitespace();
        }
        n.assert(n.peek() === "]", 'expected "]"'), n.consume(), e.push(ps(o, s));
      }
    }
  }
  return r.length > 0 && e.push(Hf(r)), { predicates: e, tags: t };
}
function hp(n) {
  const t = new fp(n, 0), e = [];
  for (; ; ) {
    const i = dp(t);
    if (e.push(i), t.skipWhitespace(), t.eof()) break;
    t.assert(t.peek() === ",", 'expected "," (selector lists are the only supported combinator)'), t.consume(), t.skipWhitespace();
  }
  if (e.length === 1) return Fr(e[0].tags, e[0].predicates);
  const r = /* @__PURE__ */ new Set();
  for (const i of e) for (const o of i.tags) r.add(o);
  return Fr(r, [(i, o) => {
    for (const s of e) {
      const l = i.nodeName;
      if (s.tags.size > 0 && !s.tags.has(l)) continue;
      let a = !0;
      for (const c of s.predicates) if (!c(i, o)) {
        a = !1;
        break;
      }
      if (a) return !0;
    }
    return !1;
  }]);
}
function ll(n, t, e) {
  return Wf(tp, n, t, e);
}
const Pr = /* @__PURE__ */ ll("textFormat", () => 0), _s = /* @__PURE__ */ ll("textStyle", () => ({}));
function gp(n) {
  if (!F(n)) return !1;
  if (n.nodeName === "PRE") return !0;
  const t = n.style.whiteSpace;
  return typeof t == "string" && t.startsWith("pre");
}
function pp(n) {
  if (Ht(n)) return !0;
  if (!F(n)) return !1;
  const t = n.style.display;
  return t ? t.startsWith("inline") : !qn(n) && zi(n);
}
const _p = /* @__PURE__ */ ll("whitespaceConfig", () => ({ isInline: pp, preservesWhitespace: gp }));
function al(n) {
  return Kn(n) || W(n) && !n.isInline();
}
function qf(n, t) {
  const e = [];
  let r = [];
  const i = () => {
    r.length !== 0 && (e.push(t().splice(0, 0, r)), r = []);
  };
  for (const o of n) if (al(o)) {
    if (i(), S(o)) {
      const s = qf(o.getChildren(), t);
      o.splice(0, o.getChildrenSize(), s);
    }
    e.push(o);
  } else r.push(o);
  return i(), e;
}
function cl(n, t) {
  if (!F(t)) return n;
  const e = t.style.textAlign;
  if (!ul(e)) return n;
  for (const r of n) Kn(r) && r.getFormatType() === "" && r.setFormat(e);
  return n;
}
function mp(n, t, e) {
  n.length === 1 && Pt(n[0]) && (n = []);
  const r = U();
  if (F(e)) {
    const i = e.style.textAlign;
    ul(i) && r.setFormat(i);
  }
  return [r.splice(0, 0, n)];
}
const Vf = { $accepts: al, $packageRun: mp, name: "BlockSchema" }, pn = Nn, yp = /* @__PURE__ */ new Set(["center", "end", "justify", "left", "right", "start"]);
function ul(n) {
  return yp.has(n);
}
const xp = { B: { fontWeight: "bold" }, EM: { fontStyle: "italic" }, I: { fontStyle: "italic" }, S: { textDecoration: "line-through" }, STRONG: { fontWeight: "bold" }, SUB: { verticalAlign: "sub" }, SUP: { verticalAlign: "super" }, U: { textDecoration: "underline" } }, Sp = { CODE: vd, MARK: ws }, Cp = /* @__PURE__ */ new Set(["font-weight", "font-style", "text-decoration", "vertical-align"]), vp = { $import: (n, t) => {
  const e = n.get(Pr), r = xp[t.nodeName], i = (function(d) {
    return { fontStyle: d.style.fontStyle, fontWeight: d.style.fontWeight, textDecoration: d.style.textDecoration, verticalAlign: d.style.verticalAlign };
  })(t), o = r ? (s = r, { fontStyle: (l = i).fontStyle || s.fontStyle, fontWeight: l.fontWeight || s.fontWeight, textDecoration: l.textDecoration || s.textDecoration, verticalAlign: l.verticalAlign || s.verticalAlign }) : i;
  var s, l;
  let a = (c = e, u = (function(d) {
    let h = 0, _ = 0;
    const { fontWeight: m, fontStyle: p, textDecoration: g, verticalAlign: y } = d;
    if (m === "700" || m === "bold" ? h |= Io : m !== "normal" && m !== "400" || (_ |= Io), p === "italic" ? h |= Ro : p === "normal" && (_ |= Ro), g) {
      const x = g.split(" ");
      x.includes("underline") && (h |= Wo), x.includes("line-through") && (h |= zo), x.includes("none") && (_ |= Wo | zo);
    }
    return y === "sub" ? (h |= go, _ |= po) : y === "super" ? (h |= po, _ |= go) : y === "baseline" && (_ |= go | po), { clear: _, set: h };
  })(o), c & ~u.clear | u.set);
  var c, u;
  const f = Sp[t.nodeName];
  return f && (a |= f), a === e ? n.$importChildren(t) : n.$importChildren(t, { context: [Ji(Pr, a)] });
}, match: pn.tag("b", "strong", "em", "i", "code", "mark", "s", "sub", "sup", "u", "span"), name: "@lexical/html/inline-format" };
function Fa(n, t, e) {
  let r = n;
  for (; ; ) {
    let i = null;
    for (; (i = t ? r.nextSibling : r.previousSibling) === null; ) {
      const s = r.parentNode;
      if (s === null) return null;
      r = s;
    }
    if (r = i, !e.isInline(r)) return null;
    let o = r;
    for (; (o = t ? r.firstChild : r.lastChild) !== null; ) r = o;
    if (Ht(r)) return r;
    if (r.nodeName === "BR") return null;
  }
}
function Pa(n, t) {
  return t !== 0 && O(n) ? n.setFormat(t) : n;
}
function Ia(n, t) {
  if (O(n)) {
    const e = (function(r) {
      let i = "";
      for (const o in r) Cp.has(o) || (i += `${o}: ${r[o]}; `);
      return i.trimEnd();
    })(t);
    e !== "" && n.setStyle(e);
  }
  return n;
}
const bp = { $import: (n, t) => {
  const e = n.get(Pr), r = n.get(_s), i = n.get(_p);
  if ((function(l, a) {
    let c = l.parentNode;
    for (; c !== null; ) {
      if (a.preservesWhitespace(c)) return !0;
      c = c.parentNode;
    }
    return !1;
  })(t, i)) {
    const l = Ls(t.textContent || "");
    for (const a of l) Pa(a, e), Ia(a, r);
    return l;
  }
  const o = (function(l, a) {
    let c = (l.textContent || "").replace(/\r/g, "").replace(/[ \t\n]+/g, " ");
    if (c.length === 0) return "";
    if (c[0] === " ") {
      let u = l, f = !0;
      for (; u !== null && (u = Fa(u, !1, a)) !== null; ) {
        const d = u.textContent || "";
        if (d.length > 0) {
          /[ \t\n]$/.test(d) && (c = c.slice(1)), f = !1;
          break;
        }
      }
      f && (c = c.slice(1));
    }
    if (c.length > 0 && c[c.length - 1] === " ") {
      let u = l, f = !0;
      for (; u !== null && (u = Fa(u, !0, a)) !== null; ) if ((u.textContent || "").replace(/^( |\t|\r?\n)+/, "").length > 0) {
        f = !1;
        break;
      }
      f && (c = c.slice(0, -1));
    }
    return c;
  })(t, i);
  if (o === "") return [];
  const s = mt(o);
  return Pa(s, e), Ia(s, r), [s];
}, match: pn.text(), name: "@lexical/html/#text" }, Tp = { $import: () => [], match: pn.tag("script", "style"), name: "@lexical/html/script-style-ignore" }, wp = { $import: (n, t) => qu(t) || Vu(t) ? [] : [Ke()], match: pn.tag("br"), name: "@lexical/html/br" }, kp = { $import: (n, t) => {
  const e = U();
  if (fe(e, t), hn(t, e), e.getFormatType() === "") {
    const r = t.getAttribute("align");
    r && ul(r) && e.setFormat(r);
  }
  return ee(e, t), [e.splice(0, 0, n.$importChildren(t))];
}, match: pn.tag("p"), name: "@lexical/html/p" }, Np = { $import: (n, t, e) => st().hasNode(co) ? [ol()] : e(), match: pn.tag("hr"), name: "@lexical/html/hr" };
pn.any();
Nn.any();
const Bt = { any: Nn.any, comment: Nn.comment, css: hp, tag: Nn.tag, text: Nn.text }, Gf = /* @__PURE__ */ new Set(["STYLE", "SCRIPT"]);
function ms(n, t) {
  Qg(t);
  const e = fn(t) ? t.body.childNodes : t.childNodes, r = [], i = [];
  for (const o of e) if (!Gf.has(o.nodeName)) {
    const s = Yf(o, n, i, !1);
    if (s !== null) for (const l of s) r.push(l);
  }
  return (function(o) {
    for (const s of o) s.getParent() && s.getNextSibling() instanceof Fs && s.insertAfter(Ke());
    for (const s of o) {
      const l = s.getParent();
      l && l.splice(s.getIndexWithinParent(), 1, s.getChildren());
    }
  })(i), r;
}
function Ep(n, t = null, e = st()) {
  return sp([Ji(np, !0)], e)(() => {
    const r = pt(), i = op(e), o = w(t) ? io(t.anchor.getNode()) : null, s = n.append.bind(n);
    for (const l of (S(o) ? o : r).getChildren()) Xf(e, l, s, t, i);
    return n;
  });
}
function ys(n, t = null) {
  return (typeof document > "u" || typeof window > "u" && global.window === void 0) && Br(338), Oh(n), Ep(V().createElement("div"), t, n).innerHTML;
}
function Xf(n, t, e, r = null, i = ro(n)) {
  let o = i.$shouldInclude(t, r, n);
  const s = i.$shouldExclude(t, r, n);
  let l = t;
  r !== null && O(t) && (l = Nf(r, t, "clone"));
  const a = i.$exportDOM(l, n), { element: c, after: u, append: f, $getChildNodes: d } = a;
  if (!c) return !1;
  const h = V().createDocumentFragment(), _ = d ? d() : S(l) ? l.getChildren() : [], m = o && nt(r) && S(t) ? null : r, p = h.append.bind(h);
  for (const g of _) {
    const y = Xf(n, g, p, m, i);
    !o && y && i.$extractWithChild(t, g, r, "html", n) && (o = !0);
  }
  if (o && !s) {
    if ((F(c) || cs(c)) && (f ? f(h) : c.append(h)), e(c), u) {
      const g = u.call(l, c);
      g && (cs(c) ? c.replaceChildren(g) : c.replaceWith(g));
    }
  } else e(h);
  return o;
}
function Yf(n, t, e, r, i = /* @__PURE__ */ new Map(), o) {
  const s = [];
  if (Gf.has(n.nodeName)) return s;
  let l = null;
  const a = (function(_, m) {
    const { nodeName: p } = _, g = m._htmlConversions.get(p.toLowerCase());
    let y = null;
    if (g !== void 0) for (const x of g) {
      const v = x(_);
      v !== null && (y === null || (y.priority || 0) <= (v.priority || 0)) && (y = v);
    }
    return y !== null ? y.conversion : null;
  })(n, t), c = a ? a(n) : null;
  let u = null;
  if (c !== null) {
    u = c.after;
    const _ = c.node;
    if (l = Array.isArray(_) ? _[_.length - 1] : _, l !== null) {
      for (const [, m] of i) if (l = m(l, o), !l) break;
      l && s.push(...Array.isArray(_) ? _ : [l]);
    }
    c.forChild != null && i.set(n.nodeName, c.forChild);
  }
  const f = n.childNodes;
  let d = [];
  const h = (l == null || !ot(l)) && (l != null && Kn(l) || r);
  for (let _ = 0; _ < f.length; _++) d.push(...Yf(f[_], t, e, h, new Map(i), l));
  if (u != null && (d = u(d)), qn(n) && (d = Op(n, d, h ? () => {
    const _ = new Fs();
    return e.push(_), _;
  } : U)), l == null) if (d.length > 0) for (const _ of d) s.push(_);
  else qn(n) && (function(_) {
    return _.nextSibling == null || _.previousSibling == null ? !1 : zi(_.nextSibling) && zi(_.previousSibling);
  })(n) && s.push(Ke());
  else S(l) && l.append(...d);
  return s;
}
function Op(n, t, e) {
  const r = n.style.textAlign, i = [];
  let o = [];
  for (let s = 0; s < t.length; s++) {
    const l = t[s];
    if (Kn(l)) r && !l.getFormat() && l.setFormat(r), i.push(l);
    else if (o.push(l), s === t.length - 1 || s < t.length - 1 && Kn(t[s + 1])) {
      const a = e();
      a.setFormat(r), a.append(...o), i.push(a), o = [];
    }
  }
  return i;
}
function ji(n, t, e = null) {
  const r = ff(e), i = e ? cf(e) : [], o = e !== null && i.length > 0;
  if (o && typeof r.caretPositionFromPoint == "function") {
    const s = r.caretPositionFromPoint(n, t, { shadowRoots: i });
    if (s !== null && (function(l, a) {
      for (let c = l; c !== null; ) {
        if (c === a) return !0;
        c = dn(c);
      }
      return !1;
    })(s.offsetNode, e)) return { node: s.offsetNode, offset: s.offset };
  }
  if (o) {
    const s = e.getRootNode();
    if (be(s)) {
      const l = s.elementFromPoint(n, t);
      if (l !== null && e.contains(l)) {
        const a = (function(c, u, f, d) {
          const h = d.createRange(), _ = (N) => u < N.top ? N.top - u : u > N.bottom ? u - N.bottom : 0, m = (N) => c < N.left ? N.left - c : c > N.right ? c - N.right : 0, p = d.createTreeWalker(f, NodeFilter.SHOW_TEXT);
          let g = null, y = 1 / 0, x = 1 / 0;
          for (let N = p.nextNode(); N; N = p.nextNode()) {
            h.selectNodeContents(N);
            for (const C of h.getClientRects()) {
              const T = _(C), A = m(C);
              (T < y || T === y && A < x) && (y = T, x = A, g = N);
            }
          }
          if (g === null) return null;
          let v = 0, E = 1 / 0, k = 1 / 0;
          for (let N = 0; N <= g.length; N++) {
            h.setStart(g, N), h.collapse(!0);
            const C = h.getBoundingClientRect(), T = _(C), A = Math.abs(c - C.left);
            (T < E || T === E && A < k) && (E = T, k = A, v = N);
          }
          return { node: g, offset: v };
        })(n, t, l, r);
        if (a !== null) return a;
      }
    }
  }
  if (typeof r.caretRangeFromPoint == "function") {
    const s = r.caretRangeFromPoint(n, t);
    return s === null ? null : { node: s.startContainer, offset: s.startOffset };
  }
  if (typeof r.caretPositionFromPoint == "function") {
    const s = r.caretPositionFromPoint(n, t);
    return s === null ? null : { node: s.offsetNode, offset: s.offset };
  }
  return null;
}
function En(n, ...t) {
  const e = new URL("https://lexical.dev/docs/error"), r = new URLSearchParams();
  r.append("code", n);
  for (const i of t) r.append("v", i);
  throw e.search = r.toString(), Error(`Minified Lexical error #${n}; visit ${e.toString()} for the full message or use the non-minified dev environment for full errors and additional helpful warnings.`);
}
const xs = { "application/x-lexical-editor": 0, "text/html": 10, "text/plain": 20, "text/uri-list": 30 };
function Ap(n) {
  return window.trustedTypes && window.trustedTypes.createPolicy ? window.trustedTypes.createPolicy("lexical", { createHTML: (t) => t }).createHTML(n) : n;
}
const Ra = (n, t) => {
  if (!w(t)) return t.insertRawText(n), !0;
  const e = (r) => {
    const i = M();
    w(i) && r(i);
  };
  return Wu(n, { linebreak: () => e((r) => r.insertParagraph()), tab: () => e((r) => r.insertNodes([Yi()])), text: (r) => e((i) => i.insertText(r)) }), !0;
}, Ss = { "application/x-lexical-editor": [(n, t, e) => {
  try {
    const r = st(), i = JSON.parse(n);
    if (i && i.namespace === r._config.namespace && Array.isArray(i.nodes))
      return za(r, Rp(i.nodes), t), !0;
  } catch (r) {
    console.error(r);
  }
  return e();
}], "text/html": [(n, t, e) => {
  try {
    const r = st(), i = new DOMParser().parseFromString(Ap(n), "text/html");
    return za(r, ms(r, i), t), !0;
  } catch (r) {
    return console.error(r), e();
  }
}], "text/plain": [Ra], "text/uri-list": [Ra] };
function Mp(n, t, e, r) {
  if (!n) return !1;
  const i = (o) => !!n[o] && n[o](t, e, i.bind(null, o - 1), r);
  return i(n.length - 1);
}
function Zf(n, t, e) {
  const r = t.getData("text/plain");
  for (const i of (function(o) {
    return Object.keys(o.$importMimeType).filter((s) => o.$importMimeType[s] !== void 0).sort((s, l) => {
      const a = o.priority[s], c = o.priority[l];
      return a === void 0 && c === void 0 ? s < l ? -1 : s > l ? 1 : 0 : a === void 0 ? 1 : c === void 0 ? -1 : a - c;
    });
  })(n)) {
    const o = t.getData(i);
    if (o && (i !== "text/html" || o !== r) && Mp(n.$importMimeType[i], o, e, t)) return !0;
  }
  return !1;
}
const Dp = { $importMimeType: Ss, $insertDataTransfer: (n, t) => Zf({ $importMimeType: Ss, priority: xs }, n, t), priority: xs }, Lp = { build: (n, t) => ({ $importMimeType: t.$importMimeType, $insertDataTransfer: (e, r) => Zf(t, e, r), priority: t.priority }), config: { $importMimeType: Ss, priority: xs }, mergeConfig(n, t) {
  const e = el(n, t);
  if (t.$importMimeType) {
    const r = { ...n.$importMimeType };
    for (const [i, o] of Object.entries(t.$importMimeType)) if (o) {
      const s = r[i];
      r[i] = s ? [...s, ...o] : o;
    }
    e.$importMimeType = r;
  }
  return t.priority && (e.priority = { ...n.priority, ...t.priority }), e;
}, name: "@lexical/clipboard/Import" };
function Qf(n, t = M()) {
  return t == null && En(166), w(t) && t.isCollapsed() || t.getNodes().length === 0 ? "" : ys(n, t);
}
function td(n, t = M()) {
  return t == null && En(166), w(t) && t.isCollapsed() || t.getNodes().length === 0 ? null : JSON.stringify(Ip(n, t));
}
function Cs(n, t, e) {
  (function() {
    const r = jg(Lp.name);
    return r ? r.output : Dp;
  })().$insertDataTransfer(n, t);
}
const ed = "application/x-lexical-drag";
function $p(n, t) {
  const e = { editorKey: t.getKey() };
  n.setData(ed, JSON.stringify(e));
}
function Fp(n, t, e) {
  const r = n.dataTransfer;
  if (r === null) return !1;
  const i = (function(c) {
    const u = c.getData(ed);
    if (!u) return null;
    let f;
    try {
      f = JSON.parse(u);
    } catch {
      return null;
    }
    return (d = f) !== null && typeof d == "object" && "editorKey" in d && typeof d.editorKey == "string" ? f : null;
    var d;
  })(r);
  if (i === null) return !1;
  const o = (function(c, u) {
    const f = ji(c.clientX, c.clientY, u.getRootElement());
    if (f === null) return null;
    const d = ve(f.node);
    if (d === null) return null;
    if (O(d)) return Re(d, "next", f.offset);
    if (S(d)) return hs(d, f.offset, "next");
    const h = d.getParent();
    return h === null ? null : hs(h, d.getIndexWithinParent() + 1, "next");
  })(n, t);
  if (o === null) return !1;
  const s = bf(o);
  if (s === null) return !1;
  const l = i.editorKey === t.getKey(), a = M();
  if (l) {
    if (!w(a) || a.isCollapsed()) return !1;
    if ((function(c, u) {
      const { anchor: f, focus: d } = tl(ds(u), "next");
      return Lr(f, c) < 0 && Lr(c, d) < 0;
    })(o, a)) return n.preventDefault(), !0;
    a.removeText();
  }
  if (!s.origin.isAttached()) return n.preventDefault(), !0;
  if (e(r, $r(lo(s)), t), !l) {
    const c = t.getRootElement(), u = c ? c.ownerDocument : null, f = u ? (function(d, h) {
      for (const _ of uf(h)) {
        const m = Kr(_);
        if (eo(m) && m.getKey() === d && F(_)) return _;
      }
      return null;
    })(i.editorKey, u) : null;
    f !== null && f.dispatchEvent(new InputEvent("beforeinput", { bubbles: !0, cancelable: !0, inputType: "deleteByDrag" }));
  }
  return n.preventDefault(), !0;
}
function Pp(n, t) {
  return Fp(n, t, Cs);
}
function za(n, t, e) {
  n.dispatchCommand(qd, { nodes: t, selection: e }) || (e.insertNodes(t), (function(r) {
    if (w(r) && r.isCollapsed()) {
      const i = r.anchor;
      let o = null;
      const s = Ft(i, "previous");
      if (s) if (Yt(s)) o = s.origin;
      else {
        const l = oe(s, Ut(pt(), "next").getFlipped());
        for (const a of l) {
          if (O(a.origin)) {
            o = a.origin;
            break;
          }
          if (S(a.origin) && !a.origin.isInline()) break;
        }
      }
      if (o && O(o)) {
        const l = o.getFormat(), a = o.getStyle();
        r.format === l && r.style === a || (r.format = l, r.style = a, r.dirty = !0);
      }
    }
  })(e));
}
function vs(n, t, e, r = []) {
  let i = t === null || e.isSelected(t);
  const o = S(e) && e.excludeFromCopy("html");
  let s = e;
  t !== null && O(s) && (s = Nf(t, s, "clone"));
  const l = S(s) ? s.getChildren() : [], a = (function(u) {
    const f = u.exportJSON(), d = u.constructor;
    if (f.type !== d.getType() && En(58, d.name), S(u)) {
      const h = f.children;
      Array.isArray(h) || En(59, d.name);
    }
    return f;
  })(s);
  O(s) && s.getTextContentSize() === 0 && (i = !1);
  const c = i && nt(t) && S(e) ? null : t;
  for (let u = 0; u < l.length; u++) {
    const f = l[u], d = vs(n, c, f, a.children);
    !i && S(e) && d && e.extractWithChild(f, t, "clone") && (i = !0);
  }
  if (i && !o) {
    const u = Xt(s);
    if (u.length > 0) {
      const f = {};
      for (const d of u) {
        const h = rn(s, d);
        h === null && En(366, s.constructor.name, d);
        const _ = [];
        vs(n, null, h, _), _.length === 1 && _[0].type === h.getType() || En(385, d, s.constructor.name, String(_.length), String(_.length > 0 ? _[0].type : "none")), f[d] = _[0];
      }
      a.$slots = f;
    }
  }
  if (i && !o) r.push(a);
  else if (Array.isArray(a.children)) for (let u = 0; u < a.children.length; u++) {
    const f = a.children[u];
    r.push(f);
  }
  return i;
}
function Ip(n, t) {
  const e = [], r = pt(), i = w(t) ? t.anchor.getNode() : nt(t) ? t.getNodes()[0] ?? null : null, o = i !== null ? io(i) : null, s = (S(o) ? o : r).getChildren();
  for (let l = 0; l < s.length; l++)
    vs(n, t, s[l], e);
  return { namespace: n._config.namespace, nodes: e };
}
function Rp(n) {
  const t = [];
  for (const e of n) t.push(Mh(e));
  return t;
}
let Sn = null;
async function Wa(n, t, e) {
  if (Sn !== null) return !1;
  if (t !== null) return new Promise((c, u) => {
    n.update(() => {
      c(Ka(n, t, e));
    });
  });
  const r = n.getRootElement(), i = n._window || window, o = i.document, s = Dt(i);
  if (r === null || s === null) return !1;
  const l = o.createElement("span");
  l.style.position = "fixed", l.style.top = "-1000px", l.append(o.createTextNode("#")), r.append(l);
  const a = o.createRange();
  return a.setStart(l, 0), a.setEnd(l, 1), s.removeAllRanges(), s.addRange(a), new Promise((c, u) => {
    const f = n.registerCommand(Xi, (d) => (Me(d, ClipboardEvent) && (f(), Sn !== null && (i.clearTimeout(Sn), Sn = null), c(Ka(n, d, e))), !0), Ih);
    Sn = i.setTimeout(() => {
      f(), Sn = null, c(!1);
    }, 50), o.execCommand("copy"), l.remove();
  });
}
function Ka(n, t, e) {
  if (e === void 0) {
    const i = Dt(n._window), o = M();
    if (!o || o.isCollapsed() || !i) return !1;
    const s = Qt(i, n.getRootElement()), l = s.anchorNode, a = s.focusNode;
    if (l !== null && a !== null && !Wr(n, l, a)) return !1;
    e = nd(o);
  }
  t.preventDefault();
  const r = t.clipboardData;
  return r !== null && (rd(r, e), !0);
}
const zp = [["text/html", Qf], ["application/x-lexical-editor", td]];
function nd(n = M()) {
  return (function(t, e) {
    const r = { "text/plain": "" };
    for (const [i, o] of Object.entries(t)) if (o) {
      const s = Kp(o, e);
      s !== null && (r[i] = s);
    }
    return r;
  })(Wp(), n);
}
function rd(n, t) {
  for (const [e] of zp) t[e] === void 0 && n.setData(e, "");
  for (const e in t) {
    const r = t[e];
    r !== void 0 && n.setData(e, r);
  }
}
function Wp(n = st()) {
  const t = ao(n, Bp.name);
  return t ? t.output : id;
}
const id = { "application/x-lexical-editor": [(n, t) => n ? td(st(), n) : t()], "text/html": [(n, t) => n ? Qf(st(), n) : t()], "text/plain": [(n, t) => n ? n.getTextContent() : t()] };
function Kp(n, t) {
  const e = (r) => n[r] ? n[r](t, e.bind(null, r - 1)) : null;
  return e(n.length - 1);
}
const Bp = { build: (n, t, e) => t.$exportMimeType, config: { $exportMimeType: id }, mergeConfig(n, t) {
  const e = el(n, t);
  if (t.$exportMimeType) {
    const r = { ...n.$exportMimeType };
    for (const [i, o] of Object.entries(t.$exportMimeType)) if (o) {
      const s = r[i];
      r[i] = s ? [...s, ...o] : o;
    }
    e.$exportMimeType = r;
  }
  return e;
}, name: "@lexical/clipboard/GetClipboardData" }, Up = { $import: (n, t) => {
  const e = Ze(t.nodeName.toLowerCase());
  return hn(t, e), fe(e, t), ee(e, t), [e.splice(0, 0, n.$importChildren(t))];
}, match: Bt.tag("h1", "h2", "h3", "h4", "h5", "h6"), name: "@lexical/rich-text/heading" }, Hp = { $import: (n, t) => {
  const e = Ur();
  return fe(e, t), hn(t, e), ee(e, t), [e.splice(0, 0, n.$importChildren(t))];
}, match: Bt.tag("blockquote"), name: "@lexical/rich-text/blockquote" };
Bt.tag("blockquote");
Bt.tag("p"), Bt.tag("span");
const Ba = /* @__PURE__ */ $("DRAG_DROP_PASTE_FILE"), Lo = /* @__PURE__ */ Fc("shadowRoot", { parse: Boolean });
let od = class sd extends Nt {
  static getType() {
    return "quote";
  }
  static clone(t) {
    return new sd(t.__key);
  }
  $config() {
    return this.config("quote", { extends: Nt, stateConfigs: [{ flat: !0, stateConfig: Lo }] });
  }
  isShadowRoot() {
    return Pc(this, Lo);
  }
  setIsShadowRoot(t) {
    return zd(this, Lo, t);
  }
  createDOM(t) {
    const e = V().createElement("blockquote");
    return Lt(e, t.theme.quote), e;
  }
  updateDOM(t, e) {
    return !1;
  }
  static importDOM() {
    return { blockquote: (t) => ({ conversion: Jp, priority: 0 }) };
  }
  exportDOM(t) {
    const { element: e } = super.exportDOM(t);
    if (F(e)) {
      this.isEmpty() && e.append(V().createElement("br"));
      const r = this.getFormatType();
      r && (e.style.textAlign = r);
      const i = this.getDirection();
      i && (e.dir = i);
    }
    return { element: e };
  }
  static importJSON(t) {
    return Ur().updateFromJSON(t);
  }
  exportJSON() {
    return super.exportJSON();
  }
  insertNewAfter(t, e) {
    const r = U(), i = this.getDirection();
    return r.setDirection(i), this.insertAfter(r, e), r;
  }
  collapseAtStart() {
    if (this.isShadowRoot()) {
      for (const e of this.getChildren()) this.insertBefore(e);
      return this.remove(), !0;
    }
    const t = U();
    return this.getChildren().forEach((e) => t.append(e)), this.replace(t), !0;
  }
  canMergeWhenEmpty() {
    return !0;
  }
};
function Ur(n) {
  const t = Mt(new od());
  return n && n.shadowRoot ? t.setIsShadowRoot(!0) : t;
}
let fl = class ld extends Nt {
  __tag;
  static getType() {
    return "heading";
  }
  static clone(t) {
    return new ld(t.__tag, t.__key);
  }
  afterCloneFrom(t) {
    super.afterCloneFrom(t), this.__tag = t.__tag;
  }
  constructor(t = "h1", e) {
    super(e), this.__tag = t;
  }
  getTag() {
    return this.getLatest().__tag;
  }
  setTag(t) {
    const e = this.getWritable();
    return e.__tag = t, e;
  }
  createDOM(t) {
    const e = this.__tag, r = V().createElement(e), i = t.theme.heading;
    if (i !== void 0) {
      const o = i[e];
      Lt(r, o);
    }
    return r;
  }
  updateDOM(t, e, r) {
    return t.__tag !== this.__tag;
  }
  static importDOM() {
    return { h1: (t) => ({ conversion: Cn, priority: 0 }), h2: (t) => ({ conversion: Cn, priority: 0 }), h3: (t) => ({ conversion: Cn, priority: 0 }), h4: (t) => ({ conversion: Cn, priority: 0 }), h5: (t) => ({ conversion: Cn, priority: 0 }), h6: (t) => ({ conversion: Cn, priority: 0 }), p: (t) => {
      const e = t.firstChild;
      return e !== null && Ua(e) ? { conversion: () => ({ node: null }), priority: 3 } : null;
    }, span: (t) => Ua(t) ? { conversion: (e) => ({ node: Ze("h1") }), priority: 3 } : null };
  }
  exportDOM(t) {
    const { element: e } = super.exportDOM(t);
    if (F(e)) {
      this.isEmpty() && e.append(V().createElement("br"));
      const r = this.getFormatType();
      r && (e.style.textAlign = r);
      const i = this.getDirection();
      i && (e.dir = i);
    }
    return { element: e };
  }
  static importJSON(t) {
    return Ze(t.tag).updateFromJSON(t);
  }
  updateFromJSON(t) {
    return super.updateFromJSON(t).setTag(t.tag);
  }
  exportJSON() {
    return { ...super.exportJSON(), tag: this.getTag() };
  }
  insertNewAfter(t, e = !0) {
    const r = t ? t.anchor.offset : 0, i = this.getLastDescendant(), o = !i || t && t.anchor.key === i.getKey() && r === i.getTextContentSize() || !t ? U() : Ze(this.getTag()), s = this.getDirection();
    if (o.setDirection(s), this.insertAfter(o, e), r === 0 && !this.isEmpty() && t) {
      const l = U();
      l.select(), this.replace(l, !0);
    }
    return o;
  }
  collapseAtStart() {
    if (this.isEmpty()) {
      const t = U();
      this.getChildren().forEach((e) => t.append(e)), this.replace(t);
    }
    return !0;
  }
  extractWithChild() {
    return !0;
  }
};
function Ua(n) {
  return n.nodeName.toLowerCase() === "span" && n.style.fontSize === "26pt";
}
function Cn(n) {
  const t = n.nodeName.toLowerCase();
  let e = null;
  return t !== "h1" && t !== "h2" && t !== "h3" && t !== "h4" && t !== "h5" && t !== "h6" || (e = Ze(t), hn(n, e), fe(e, n), ee(e, n)), { node: e };
}
function Jp(n) {
  const t = Ur();
  return fe(t, n), hn(n, t), ee(t, n), { node: t };
}
function Ze(n = "h1") {
  return Mt(new fl(n));
}
function jp(n) {
  return n instanceof fl;
}
function Ha(n) {
  const t = ve(n);
  return W(t);
}
function vn(n, t, e, r) {
  let i = !1, o = null;
  if (n.isCollapsed() && n.anchor.type === "text") {
    const l = n.anchor.getNode();
    if (O(l)) {
      o = l;
      const a = n.anchor.offset, c = a === l.getTextContentSize() && l.getNextSibling() === null, u = a === 0 && l.getPreviousSibling() === null;
      i = e === "end" && c || e === "start" && u || e === "both" && (c || u);
    }
  }
  let s = !1;
  for (const [l, a] of Object.entries(r)) {
    if (a == null || !a[t]) continue;
    const c = l;
    if (a.onlyAtBoundary) {
      if (!(i && o && O(o) && o.hasFormat(c))) continue;
      s = !0;
    }
    n.hasFormat(c) && n.toggleFormat(c);
  }
  s && n.setStyle("");
}
const qp = { capitalize: { enter: !0, space: !0, tab: !0 }, lowercase: { enter: !0, space: !0, tab: !0 }, uppercase: { enter: !0, space: !0, tab: !0 } };
function si(n, t) {
  return (function(e, r) {
    if (!e.isCollapsed()) return !1;
    const i = Ft(e.focus, r), o = dt(i.origin, Fn);
    if (!o) return !1;
    const s = e.focus.getNode();
    if (!o.is(s) && !jn(s, o)) return !1;
    const l = oe(i, G(o, r));
    if (l.getTextSlices().some((f) => f && f.getTextContentSize() > 0)) return !1;
    const a = oe(l.anchor.getSiblingCaret(), l.focus);
    let c = a.anchor.origin;
    for (const f of a) {
      if (!Te(f) || !f.origin.is(c.getParent())) return !1;
      c = f.origin;
    }
    let u = o;
    for (const f of so(G(o, r))) {
      if (!f.origin.is(u.getParent())) {
        if (mi(f.origin)) {
          const d = G(u, r);
          return $r(oe(d, d)), !0;
        }
        break;
      }
      if (!Fn(f.origin)) break;
      u = f.origin;
    }
    return !1;
  })(n, t) || (function(e, r) {
    if (!e.isCollapsed() || e.anchor.type !== "element") return !1;
    const i = Ft(e.anchor, r).getNodeAtCaret();
    return !(!Fn(i) || i.isInline() || ($r(lo(Wt(Ut(i, r)))), 0));
  })(n, t);
}
function Ja(n) {
  return W(n) && !n.isInline() && !n.isIsolated() && n.isKeyboardSelectable();
}
function li(n) {
  const t = Ai();
  t.add(n), kt(t);
}
function ja(n, t) {
  if (!n.isCollapsed()) return !1;
  const e = n.focus, r = e.getNode(), i = t ? "previous" : "next", o = Ft(e, i);
  if (e.type === "element" && S(r) && (ut(r) || Fn(r))) {
    const g = o.getNodeAtCaret();
    return !(g === null || !Ja(g)) && (li(g.__key), !0);
  }
  const s = dt(S(r) ? r : r.getParentOrThrow(), (g) => S(g) && !g.isInline() && ot(g.getParent()));
  if (s === null) return !1;
  const l = G(s, i).getNodeAtCaret();
  if (l === null || !Ja(l)) return !1;
  if (s.getTextContentSize() === 0) return li(l.__key), !0;
  const a = st().getRootElement();
  if (a === null) return !1;
  const c = Dt(a.ownerDocument.defaultView);
  if (c === null || c.rangeCount === 0) return !1;
  const u = c.anchorNode, f = c.anchorOffset, d = c.focusNode, h = c.focusOffset;
  c.modify("move", t ? "backward" : "forward", "line");
  const _ = c.anchorNode, m = c.anchorOffset;
  if (_ === null) return qa(c, u, f, d, h), !1;
  const p = ve(_);
  return qa(c, u, f, d, h), p === null ? !1 : (_ === u && m === f || !p.is(s) && !jn(p, s)) && (li(l.__key), !0);
}
function qa(n, t, e, r, i) {
  t !== null && r !== null && n.setBaseAndExtent(t, e, r, i);
}
function Va(n, t) {
  if (!n.isCollapsed()) return !1;
  const e = n.focus.getNode(), r = dt(S(e) ? e : e.getParentOrThrow(), (p) => S(p) && !p.isInline());
  if (r === null) return !1;
  const i = st(), o = i.getRootElement();
  if (o === null) return !1;
  const s = o.ownerDocument.defaultView;
  if (s === null) return !1;
  let l = !1;
  for (const p of r.getChildren()) if (S(p) && p.isInline()) {
    const g = i.getElementByKey(p.getKey());
    if (g !== null) {
      const y = s.getComputedStyle(g).display;
      if (y === "inline-grid" || y === "inline-flex") {
        l = !0;
        break;
      }
    }
  }
  if (!l) return !1;
  const a = G(r, t ? "previous" : "next").getNodeAtCaret();
  if (a === null || !S(a)) {
    if (t) {
      const p = r.getFirstDescendant();
      O(p) ? p.select(0, 0) : r.select(0, 0);
    } else {
      const p = r.getLastDescendant();
      if (O(p)) {
        const g = p.getTextContentSize();
        p.select(g, g);
      } else {
        const g = r.getChildrenSize();
        r.select(g, g);
      }
    }
    return !0;
  }
  const c = i.getElementByKey(a.getKey());
  if (c === null) return !1;
  const u = Dt(s);
  if (u === null || u.rangeCount === 0) return !1;
  const f = u.getRangeAt(0).cloneRange();
  f.collapse(!0);
  const d = f.getBoundingClientRect(), h = c.getBoundingClientRect(), _ = h.top + h.height / 2;
  if (d.height > 0) {
    const p = ji(d.left, _, o);
    if (p !== null && c.contains(p.node)) {
      const g = o.ownerDocument.createRange();
      return g.setStart(p.node, p.offset), g.collapse(!0), n.applyDOMRange(g), n.dirty = !0, !0;
    }
  }
  const m = t ? a.getLastDescendant() : a.getFirstDescendant();
  if (O(m)) {
    const p = t ? m.getTextContentSize() : 0;
    m.select(p, p);
  } else {
    const p = a.getChildrenSize();
    a.select(t ? p : 0, t ? p : 0);
  }
  return !0;
}
function ai(n, t) {
  const e = G(n, t), r = e.getAdjacentCaret();
  r !== null && S(r.origin) && !r.origin.isInline() && r.origin.isShadowRoot() ? $r(lo(e)) : t === "next" ? n.selectNext(0, 0) : n.selectPrevious();
}
function Ga(n, t, e) {
  e.preventDefault(), e.stopPropagation();
  const r = n.getNodes();
  if (r.length === 0) return !0;
  const i = r.map((a) => G(a, "next")).sort(Lr), o = (t ? i[0] : i[i.length - 1]).origin, s = dt(o, (a) => a !== o && S(a) && !a.isInline()) ?? pt(), l = t ? 0 : s.getChildrenSize();
  return s.select(l, l), !0;
}
function Vp(n, t = $g(qp)) {
  return an(n.registerCommand(Gc, () => {
    const e = M();
    return nt(e) ? (e.clear(), !0) : (w(e) && vn(e, "click", "both", t.peek()), !1);
  }, R), n.registerCommand(Ge, (e) => {
    const r = M();
    return w(r) ? (r.deleteCharacter(e), !0) : !!nt(r) && (r.deleteNodes(), !0);
  }, R), n.registerCommand(kr, (e) => {
    const r = M();
    return !!w(r) && (r.deleteWord(e), !0);
  }, R), n.registerCommand(Nr, (e) => {
    const r = M();
    return !!w(r) && (r.deleteLine(e), !0);
  }, R), n.registerCommand(Mn, (e) => {
    const r = M();
    if (typeof e == "string") r !== null && r.insertText(e);
    else {
      if (r === null) return !1;
      const i = e.dataTransfer;
      if (i != null) Cs(i, r);
      else if (w(r)) {
        const o = e.data;
        return o && r.insertText(o), !0;
      }
    }
    return !0;
  }, R), n.registerCommand(di, () => {
    const e = M();
    return !!w(e) && (e.removeText(), !0);
  }, R), n.registerCommand(Ot, (e) => {
    const r = M();
    return !(!w(r) && !nt(r)) && (Au(r, e), !0);
  }, R), n.registerCommand(Vd, (e) => {
    const r = M();
    return !(!w(r) && !nt(r)) && (Th(r, e), !0);
  }, R), n.registerCommand(du, (e) => {
    const r = M();
    if (!w(r) && !nt(r)) return !1;
    const i = r.getNodes();
    for (const o of i) {
      const s = dt(o, (l) => S(l) && !l.isInline());
      s !== null && s.setFormat(e);
    }
    return !0;
  }, R), n.registerCommand(An, (e) => {
    const r = M();
    return !!w(r) && (r.insertLineBreak(e), !0);
  }, R), n.registerCommand(wr, () => {
    const e = M();
    return !!w(e) && (e.insertParagraph(), !0);
  }, R), n.registerCommand(Gd, () => {
    const e = Yi(), r = M();
    return w(r) && (e.setFormat(r.format), e.setStyle(r.style)), Nh([e]), !0;
  }, R), n.registerCommand(Xd, () => Na((e) => {
    const r = e.getIndent();
    e.setIndent(r + 1);
  }), R), n.registerCommand(Pl, () => Na((e) => {
    const r = e.getIndent();
    r > 0 && e.setIndent(Math.max(0, r - 1));
  }), R), n.registerCommand(ou, (e) => {
    const r = M();
    if (nt(r)) {
      const i = r.getNodes();
      if (i.length > 0) return e.preventDefault(), ai(i[0], "previous"), !0;
    } else if (w(r) && (!e.shiftKey && si(r, "previous") || !e.shiftKey && ja(r, !0) || !e.shiftKey && Va(r, !0)))
      return e.preventDefault(), !0;
    return !1;
  }, R), n.registerCommand(su, (e) => {
    const r = M();
    if (nt(r)) {
      const i = r.getNodes();
      if (i.length > 0) return e.preventDefault(), ai(i[0], "next"), !0;
    } else if (w(r) && ((function(i) {
      const o = i.focus;
      return o.key === "root" && o.offset === pt().getChildrenSize();
    })(r) || !e.shiftKey && si(r, "next") || !e.shiftKey && ja(r, !1) || !e.shiftKey && Va(r, !1)))
      return e.preventDefault(), !0;
    return !1;
  }, R), n.registerCommand(ru, (e) => {
    const r = M();
    if (nt(r)) {
      const i = r.getNodes();
      if (i.length > 0) return e.preventDefault(), ai(i[0], ni(i[0]) ? "next" : "previous"), !0;
    }
    if (!w(r)) return !1;
    if (!e.shiftKey && si(r, ni(r.anchor.getNode()) ? "next" : "previous")) return e.preventDefault(), !0;
    if (e.shiftKey || vn(r, "arrow", "start", t.peek()), wa(r, !0)) {
      const i = e.shiftKey;
      return e.preventDefault(), ka(r, i, !0), !0;
    }
    return !1;
  }, R), n.registerCommand(eu, (e) => {
    const r = M();
    if (nt(r)) {
      const i = r.getNodes();
      if (i.length > 0) return e.preventDefault(), ai(i[0], ni(i[0]) ? "previous" : "next"), !0;
    }
    if (!w(r)) return !1;
    if (!e.shiftKey && si(r, ni(r.anchor.getNode()) ? "previous" : "next")) return e.preventDefault(), !0;
    if (e.shiftKey || vn(r, "arrow", "end", t.peek()), wa(r, !1)) {
      const i = e.shiftKey;
      return e.preventDefault(), ka(r, i, !1), !0;
    }
    return !1;
  }, R), n.registerCommand(Os, (e) => {
    const r = M();
    if (!nt(r) && Ha(e.target)) return !1;
    if (w(r)) {
      if ((function(i) {
        if (!i.isCollapsed()) return !1;
        const { anchor: o } = i;
        if (o.offset !== 0) return !1;
        const s = o.getNode();
        if (ut(s)) return !1;
        const l = Ag(s);
        return l.getIndent() > 0 && (l.is(s) || s.is(l.getFirstDescendant()));
      })(r)) return e.preventDefault(), n.dispatchCommand(Pl, void 0);
      if (Pe && Wn) return !1;
    } else if (!nt(r)) return !1;
    return e.preventDefault(), n.dispatchCommand(Ge, !0);
  }, R), n.registerCommand(cu, (e) => {
    const r = M();
    return !(!nt(r) && Ha(e.target)) && !(!w(r) && !nt(r)) && (e.preventDefault(), n.dispatchCommand(Ge, !1));
  }, R), n.registerCommand(Ti, (e) => {
    let r = M();
    if (nt(r)) {
      const i = r.getNodes();
      i.length === 1 && W(i[0]) && !i[0].isInline() && (r = i[0].selectNext());
    }
    if (!w(r)) return !1;
    if (vn(r, "enter", "both", t.peek()), e !== null) {
      if ((Pe || Ir || Rr) && Wn) return !1;
      if (e.preventDefault(), e.shiftKey) return n.dispatchCommand(An, !1);
    }
    return n.dispatchCommand(wr, void 0);
  }, R), n.registerCommand(au, () => {
    const e = M();
    return !!w(e) && (n.blur(), !0);
  }, R), n.registerCommand(fu, (e) => {
    const [, r] = ri(e);
    if (r.length > 0) {
      const i = e.clientX, o = e.clientY, s = ji(i, o, n.getRootElement());
      if (s !== null) {
        const { offset: l, node: a } = s, c = ve(a);
        if (c !== null) {
          const u = zu();
          if (O(c)) u.anchor.set(c.getKey(), l, "text"), u.focus.set(c.getKey(), l, "text");
          else {
            const d = c.getParentOrThrow().getKey(), h = c.getIndexWithinParent() + 1;
            u.anchor.set(d, h, "element"), u.focus.set(d, h, "element");
          }
          const f = wn(u);
          kt(f);
        }
        n.dispatchCommand(Ba, r);
      }
      return e.preventDefault(), !0;
    }
    return Pp(e, n);
  }, R), n.registerCommand(hu, (e) => {
    const [r] = ri(e), i = M();
    return !(r && !w(i)) && (w(i) && !i.isCollapsed() && e.dataTransfer !== null && (rd(e.dataTransfer, nd(i)), $p(e.dataTransfer, n)), !0);
  }, R), n.registerCommand(gu, (e) => {
    const [r] = ri(e), i = M();
    if (r && !w(i)) return !1;
    const o = e.clientX, s = e.clientY, l = ji(o, s, n.getRootElement());
    if (l !== null) {
      const a = ve(l.node);
      W(a) && e.preventDefault();
    }
    return !0;
  }, R), n.registerCommand(pu, () => {
    const e = M();
    return Jh(w(e) && io(e.anchor.getNode()) !== null ? e : null), !0;
  }, R), n.registerCommand(Xi, (e) => (Wa(n, Me(e, ClipboardEvent) ? e : null), !0), R), n.registerCommand(As, (e) => ((async function(r, i) {
    await Wa(i, Me(r, ClipboardEvent) ? r : null), i.update(() => {
      const o = M();
      w(o) ? o.removeText() : nt(o) && o.getNodes().forEach((s) => s.remove());
    }, { tag: Tu });
  })(e, n), !0), R), n.registerCommand(Es, (e) => {
    const [, r, i] = ri(e);
    return r.length > 0 && !i ? (n.dispatchCommand(Ba, r), !0) : nr(e.target) && Rs(e.target) ? !1 : M() !== null && ((function(o, s) {
      o.preventDefault(), s.update(() => {
        const l = M(), a = Me(o, InputEvent) || Me(o, KeyboardEvent) ? null : o.clipboardData;
        a != null && l !== null && Cs(a, l);
      }, { tag: bu });
    })(e, n), !0);
  }, R), n.registerCommand(lu, () => {
    const e = M();
    return w(e) && vn(e, "space", "both", t.peek()), !1;
  }, R), n.registerCommand(uu, () => {
    const e = M();
    return w(e) && vn(e, "tab", "both", t.peek()), !1;
  }, R), n.registerCommand(nu, (e) => {
    const r = M();
    if (nt(r)) return Ga(r, !1, e);
    if (!w(r)) return !1;
    const { anchor: i } = r;
    if (i.type !== "element" || i.offset !== 0) return !1;
    const o = i.getNode();
    if (!S(o)) return !1;
    const s = o.getFirstChild();
    if (!W(s) || !s.isInline()) return !1;
    const l = o.getKey(), a = o.selectEnd();
    return e.shiftKey && a.anchor.set(l, 0, "element"), e.preventDefault(), e.stopPropagation(), !0;
  }, R), n.registerCommand(iu, (e) => {
    const r = M();
    if (nt(r)) return Ga(r, !0, e);
    if (!w(r)) return !1;
    const { anchor: i, focus: o } = r, s = dt(o.getNode(), (c) => S(c) && !c.isInline());
    if (s === null) return !1;
    const l = s.getFirstChild();
    if (!W(l) || !l.isInline() || dt(i.getNode(), (c) => S(c) && !c.isInline()) !== s) return !1;
    const a = s.getKey();
    return (o.type !== "element" || o.key !== a || o.offset !== 0) && (r.focus.set(a, 0, "element"), e.shiftKey || r.anchor.set(a, 0, "element"), e.preventDefault(), e.stopPropagation(), !0);
  }, R));
}
function Gp(n, t, e, r, i) {
  if (n === null || e.size === 0 && r.size === 0 && !i) return 0;
  const o = t._selection, s = n._selection;
  if (i) return 1;
  if (!(w(o) && w(s) && s.isCollapsed() && o.isCollapsed())) return 0;
  const l = (function(g, y, x) {
    const v = g._nodeMap, E = [];
    for (const k of y) {
      const N = v.get(k);
      N !== void 0 && E.push(N);
    }
    for (const [k, N] of x) {
      if (!N) continue;
      const C = v.get(k);
      C === void 0 || ut(C) || E.push(C);
    }
    return E;
  })(t, e, r);
  if (l.length === 0) return 0;
  if (l.length > 1) {
    const g = t._nodeMap, y = g.get(o.anchor.key), x = g.get(s.anchor.key);
    return y && x && !n._nodeMap.has(y.__key) && O(y) && y.__text.length === 1 && o.anchor.offset === 1 ? 2 : 0;
  }
  const a = l[0], c = n._nodeMap.get(a.__key);
  if (!O(c) || !O(a) || c.__mode !== a.__mode) return 0;
  const u = c.__text, f = a.__text;
  if (u === f) return 0;
  const d = o.anchor, h = s.anchor;
  if (d.key !== h.key || d.type !== "text") return 0;
  const _ = d.offset, m = h.offset, p = f.length - u.length;
  return p === 1 && m === _ - 1 ? 2 : p === -1 && m === _ + 1 ? 3 : p === -1 && m === _ ? 4 : 0;
}
function Xp(n, t, e) {
  let r = e(), i = 0, o = r, s = 0, l = null;
  return (a, c, u, f, d, h) => {
    const _ = e();
    if (h.has(wu) && (o = r, s = i, l = a), h.has(ts)) return i = 0, r = _, 2;
    h.has(Ei) && l && (r = o, i = s, a = l);
    const m = h.has(bu) || h.has(Tu) ? 0 : Gp(a, c, f, d, n.isComposing()), p = (() => {
      const g = u === null || u.editor === n, y = h.has(dh);
      if (!y && g && h.has(_r)) return 0;
      if (m === 1) return 2;
      if (a === null) return 1;
      const x = c._selection;
      return f.size > 0 || d.size > 0 ? y === !1 && m !== 0 && m === i && _ < r + t && g || f.size === 1 && (function(E, k, N) {
        const C = k._nodeMap.get(E), T = N._nodeMap.get(E), A = k._selection, D = N._selection;
        return !(w(A) && w(D) && A.anchor.type === "element" && A.focus.type === "element" && D.anchor.type === "text" && D.focus.type === "text" || !O(C) || !O(T) || C.__parent !== T.__parent) && JSON.stringify(k.read(() => C.exportJSON())) === JSON.stringify(N.read(() => T.exportJSON()));
      })(Array.from(f)[0], a, c) ? 0 : 1 : x !== null ? 0 : 2;
    })();
    return r = _, i = m, p;
  };
}
function Xa(n, t) {
  n.undoStack = [], n.redoStack = [], n.current = null;
}
function Yp(n, t, e, r = Date.now, i, o = null) {
  const s = Xp(n, e, r);
  return an(n.registerCommand(Vi, () => ((function(l, a, c) {
    const u = a.redoStack, f = a.undoStack;
    if (f.length !== 0) {
      const d = a.current, h = f.pop();
      d !== null && (u.push(d), l.dispatchCommand(Yr, !0)), f.length === 0 && l.dispatchCommand(Zr, !1), a.current = h || null, h && h.editor.setEditorState(h.editorState, { tag: ts });
    }
  })(n, t), !0), R), n.registerCommand(Gi, () => ((function(l, a, c) {
    const u = a.redoStack, f = a.undoStack;
    if (u.length !== 0) {
      const d = a.current;
      d !== null && (f.push(d), l.dispatchCommand(Zr, !0));
      const h = u.pop();
      u.length === 0 && l.dispatchCommand(Yr, !1), a.current = h || null, h && h.editor.setEditorState(h.editorState, { tag: ts });
    }
  })(n, t), !0), R), n.registerCommand(Zd, () => (Xa(t), !1), R), n.registerCommand(Qd, () => (Xa(t), n.dispatchCommand(Yr, !1), n.dispatchCommand(Zr, !1), !0), R), n.registerUpdateListener(({ editorState: l, prevEditorState: a, dirtyLeaves: c, dirtyElements: u, tags: f }) => {
    const d = t.current, h = t.redoStack, _ = t.undoStack, m = d === null ? null : d.editorState;
    if (d !== null && l === m) return;
    const p = s(a, l, d, c, u, f);
    if (p === 1) {
      if (h.length !== 0 && (t.redoStack = [], n.dispatchCommand(Yr, !1)), d !== null) {
        _.push({ ...d });
        const g = typeof o == "number" || o === null ? o : o.peek();
        g !== null && _.length > g && _.splice(0, _.length - g), n.dispatchCommand(Zr, !0);
      }
    } else if (p === 2) return;
    t.current = { editor: n, editorState: l };
  }));
}
function Zp() {
  return { current: null, redoStack: [], undoStack: [] };
}
function Oe(n, ...t) {
  const e = new URL("https://lexical.dev/docs/error"), r = new URLSearchParams();
  r.append("code", n);
  for (const i of t) r.append("v", i);
  throw e.search = r.toString(), Error(`Minified Lexical error #${n}; visit ${e.toString()} for the full message or use the non-minified dev environment for full errors and additional helpful warnings.`);
}
function Qp(n) {
  let t = 1, e = n.getParent();
  for (; e != null; ) {
    if (q(e)) {
      const r = e.getParent();
      if (K(r)) {
        t++, e = r.getParent();
        continue;
      }
      Oe(40);
    }
    return t;
  }
  return t;
}
function bs(n) {
  const t = n.getParent();
  K(t) || Oe(40);
  let e = t, r = t;
  for (; r !== null; ) r = r.getParent(), K(r) && (e = r);
  return e;
}
function ad(n) {
  let t = [];
  const e = n.getChildren().filter(q);
  for (let r = 0; r < e.length; r++) {
    const i = e[r], o = i.getFirstChild();
    K(o) ? t = t.concat(ad(o)) : t.push(i);
  }
  return t;
}
function Vt(n) {
  return q(n) && K(n.getFirstChild());
}
function cd(n, t) {
  return q(n) && (t.length === 0 || t.length === 1 && n.is(t[0]) && n.getChildrenSize() === 0);
}
function Ya(n) {
  const t = M();
  if (t !== null) {
    let e = t.getNodes();
    if (w(t)) {
      const [i] = t.getStartEndPoints(), o = i.getNode(), s = o.getParent();
      if (ot(o)) {
        const l = o.getFirstChild();
        if (l) e = l.selectStart().getNodes();
        else {
          const a = U();
          o.append(a), e = a.select().getNodes();
        }
      } else if (cd(o, e)) {
        const l = ce(n);
        if (ot(s)) {
          o.replace(l);
          const a = ke();
          S(o) && (a.setFormat(o.getFormatType()), a.setIndent(o.getIndent())), l.append(a);
        } else if (q(o)) {
          const a = o.getParentOrThrow();
          cn(l, a.getChildren()), a.replace(l);
        }
        return;
      }
    }
    const r = /* @__PURE__ */ new Set();
    for (let i = 0; i < e.length; i++) {
      const o = e[i];
      if (S(o) && o.isEmpty() && !q(o) && !r.has(o.getKey())) {
        Za(o, n);
        continue;
      }
      let s = Zu(o) ? o.getParent() : q(o) && o.isEmpty() ? o : null;
      for (; s != null; ) {
        const l = s.getKey();
        if (K(s)) {
          if (!r.has(l)) {
            const a = ce(n);
            cn(a, s.getChildren()), s.replace(a), r.add(l);
          }
          break;
        }
        {
          const a = s.getParent();
          if (ot(a) && !r.has(l)) {
            r.add(l), Za(s, n);
            break;
          }
          s = a;
        }
      }
    }
  }
}
function cn(n, t) {
  n.splice(n.getChildrenSize(), 0, t);
}
function Za(n, t) {
  if (K(n)) return n;
  const e = n.getPreviousSibling(), r = n.getNextSibling(), i = ke();
  let o;
  if (cn(i, n.getChildren()), K(e) && t === e.getListType()) e.append(i), K(r) && t === r.getListType() && (cn(e, r.getChildren()), r.remove()), o = e;
  else if (K(r) && t === r.getListType()) r.getFirstChildOrThrow().insertBefore(i), o = r;
  else {
    const l = ce(t);
    l.append(i), n.replace(l), o = l;
  }
  i.setFormat(n.getFormatType()), i.setIndent(n.getIndent());
  const s = M();
  return w(s) && (o.getKey() === s.anchor.key && s.anchor.set(i.getKey(), s.anchor.offset, "element"), o.getKey() === s.focus.key && s.focus.set(i.getKey(), s.focus.offset, "element")), n.remove(), o;
}
function dl(n, t) {
  const e = n.getLastChild(), r = t.getFirstChild();
  e && r && Vt(e) && Vt(r) && (dl(e.getFirstChild(), r.getFirstChild()), r.remove());
  const i = t.getChildren();
  i.length > 0 && n.append(...i), t.remove();
}
function t_() {
  const n = M();
  if (w(n)) {
    const t = /* @__PURE__ */ new Set(), e = n.getNodes(), r = n.anchor.getNode();
    if (cd(r, e)) t.add(bs(r));
    else for (let i = 0; i < e.length; i++) {
      const o = e[i];
      if (Zu(o)) {
        const s = Og(o, Hr);
        s != null && t.add(bs(s));
      }
    }
    for (const i of t) {
      let o = i;
      const s = ad(i);
      for (const l of s) {
        const a = U().setTextStyle(n.style).setTextFormat(n.format);
        cn(a, l.getChildren()), o.insertAfter(a), o = a, l.__key === n.anchor.key && ln(n.anchor, Wt(Ut(a, "next"))), l.__key === n.focus.key && ln(n.focus, Wt(Ut(a, "next"))), l.remove();
      }
      i.remove();
    }
  }
}
function ud(n) {
  const t = n.getListType() !== "check";
  let e = n.getStart();
  for (const r of n.getChildren()) q(r) && (r.getValue() !== e && r.setValue(e), t && r.getLatest().__checked != null && r.setChecked(void 0), K(r.getFirstChild()) || e++);
}
function e_(n) {
  const t = /* @__PURE__ */ new Set();
  if (Vt(n) || t.has(n.getKey())) return;
  const e = n.getParent(), r = n.getNextSibling(), i = n.getPreviousSibling();
  if (Vt(r) && Vt(i)) {
    const o = i.getFirstChild();
    if (K(o)) {
      o.append(n);
      const s = r.getFirstChild();
      K(s) && (cn(o, s.getChildren()), r.remove(), t.add(r.getKey()));
    }
  } else if (Vt(r)) {
    const o = r.getFirstChild();
    if (K(o)) {
      const s = o.getFirstChild();
      s !== null && s.insertBefore(n);
    }
  } else if (Vt(i)) {
    const o = i.getFirstChild();
    K(o) && o.append(n);
  } else if (K(e)) {
    const o = At(n), s = At(e);
    o.append(s), s.append(n), i ? i.insertAfter(o) : r ? r.insertBefore(o) : e.append(o);
  }
}
function Qa(n) {
  if (Vt(n)) return;
  const t = n.getParent(), e = t ? t.getParent() : void 0;
  if (K(e ? e.getParent() : void 0) && q(e) && K(t)) {
    const r = t ? t.getFirstChild() : void 0, i = t ? t.getLastChild() : void 0;
    if (n.is(r)) e.insertBefore(n), t.isEmpty() && e.remove();
    else if (n.is(i)) e.insertAfter(n), t.isEmpty() && e.remove();
    else {
      const o = At(n), s = At(t);
      o.append(s), n.getPreviousSiblings().forEach((c) => s.append(c));
      const l = At(n), a = At(t);
      l.append(a), cn(a, n.getNextSiblings()), e.insertBefore(o), e.insertAfter(l), e.replace(n);
    }
  }
}
function n_(n = !1) {
  const t = M();
  if (!w(t) || !t.isCollapsed()) return !1;
  const e = t.anchor.getNode();
  let r = null;
  if (q(e) && e.getChildrenSize() === 0) r = e;
  else if (O(e)) {
    const c = e.getParent();
    q(c) && c.getChildren().every((u) => O(u) && u.getTextContent().trim() === "") && (r = c);
  }
  if (r === null) return !1;
  const i = bs(r), o = r.getParent();
  K(o) || Oe(40);
  const s = o.getParent();
  let l;
  if (ot(s)) l = U(), i.insertAfter(l);
  else {
    if (!q(s)) return !1;
    l = At(s), s.insertAfter(l);
  }
  l.setTextStyle(t.style).setTextFormat(t.format).select();
  const a = r.getNextSiblings();
  if (a.length > 0) {
    const c = n ? (function(f, d) {
      return f.getStart() + d.getIndexWithinParent();
    })(o, r) : 1, u = At(o).setStart(c);
    if (q(l)) {
      const f = At(l);
      f.append(u), l.insertAfter(f);
    } else l.insertAfter(u);
    u.append(...a);
  }
  return (function(c) {
    let u = c;
    for (; u.getNextSibling() == null && u.getPreviousSibling() == null; ) {
      const f = u.getParent();
      if (f == null || !q(f) && !K(f)) break;
      u = f;
    }
    u.remove();
  })(r), !0;
}
class Hr extends Nt {
  __value;
  __checked;
  $config() {
    return this.config("listitem", { $transform: (t) => {
      const e = t.getParent();
      if (K(e)) e.getListType() !== "check" && t.getChecked() != null && t.setChecked(void 0);
      else if (e) {
        const r = t.createParentElementNode();
        K(r) || Oe(340);
        const i = [t];
        for (const o of ["previous", "next"]) {
          i.reverse();
          for (const { origin: s } of G(t, o)) {
            if (!q(s)) break;
            i.push(s);
          }
        }
        t.insertBefore(r), r.splice(0, 0, i), ot(e) || (Tf(r, gn(G(r, "next")), { $shouldSplit: () => !1, removeEmptyDestination: !0 }), e.isEmpty() && e.isAttached() && e.remove());
      }
    }, extends: Nt, importDOM: { li: () => ({ conversion: r_, priority: 0 }) } });
  }
  constructor(t = 1, e = void 0, r) {
    super(r), this.__value = t === void 0 ? 1 : t, this.__checked = e;
  }
  afterCloneFrom(t) {
    super.afterCloneFrom(t), this.__value = t.__value, this.__checked = t.__checked;
  }
  createDOM(t) {
    const e = V().createElement("li");
    return this.updateListItemDOM(null, e, t), e;
  }
  updateListItemDOM(t, e, r) {
    (function(s, l) {
      const a = l.getParent();
      !K(a) || a.getListType() !== "check" || K(l.getFirstChild()) ? (s.removeAttribute("role"), s.removeAttribute("tabIndex"), s.removeAttribute("aria-checked")) : (s.setAttribute("role", "checkbox"), s.setAttribute("tabIndex", "-1"), s.setAttribute("aria-checked", l.getChecked() ? "true" : "false"));
    })(e, this), e.value = this.__value, (function(s, l, a) {
      const c = l.list;
      if (!c) return;
      const u = c.listitem, f = c.nested && c.nested.listitem, d = a.getParent(), h = K(d) && d.getListType() === "check", _ = a.getChecked(), m = a.getChildren().some((y) => K(y)), p = [];
      c.listitemChecked !== void 0 && p.push(c.listitemChecked), c.listitemUnchecked !== void 0 && p.push(c.listitemUnchecked), f !== void 0 && p.push(...Ce(f)), p.length > 0 && Pn(s, ...p);
      const g = [];
      if (u !== void 0 && g.push(...Ce(u)), h) {
        const y = _ ? c.listitemChecked : c.listitemUnchecked;
        y !== void 0 && g.push(y);
      }
      f !== void 0 && m && g.push(...Ce(f)), g.length > 0 && Lt(s, ...g);
    })(e, r.theme, this);
    const i = t ? t.__style : "", o = this.__style;
    i !== o && Or(e.style, o, i), (function(s, l, a) {
      const c = l.__textStyle, u = a ? a.__textStyle : "";
      if (a !== null && u === c) return;
      const f = Oi(c);
      for (const d in f) s.style.setProperty(`--listitem-marker-${d}`, f[d]);
      if (u !== "") for (const d in Oi(u)) d in f || s.style.removeProperty(`--listitem-marker-${d}`);
    })(e, this, t);
  }
  updateDOM(t, e, r) {
    const i = e;
    return this.updateListItemDOM(t, i, r), !1;
  }
  updateFromJSON(t) {
    return super.updateFromJSON(t).setValue(t.value).setChecked(t.checked);
  }
  exportDOM(t) {
    const e = this.createDOM(t._config), r = this.getFormatType();
    r && (e.style.textAlign = r);
    const i = this.getDirection();
    return i && (e.dir = i), Vt(this) ? { after(o) {
      if (F(o)) {
        const s = o.previousElementSibling;
        if (F(s) && s.nodeName === "LI") {
          for (; o.firstChild; ) s.append(o.firstChild);
          o.remove();
        }
      }
      return o;
    }, element: e } : { element: e };
  }
  exportJSON() {
    return { ...super.exportJSON(), checked: this.getChecked(), value: this.getValue() };
  }
  append(...t) {
    for (let e = 0; e < t.length; e++) {
      const r = t[e];
      if (S(r) && this.canMergeWith(r)) {
        const i = r.getChildren();
        this.append(...i), r.remove();
      } else super.append(r);
    }
    return this;
  }
  replace(t, e) {
    if (q(t)) return super.replace(t);
    this.setIndent(0);
    const r = this.getParentOrThrow();
    if (!K(r)) return t;
    if (r.__first === this.getKey()) r.insertBefore(t);
    else if (r.__last === this.getKey()) r.insertAfter(t);
    else {
      const s = At(r);
      let l = this.getNextSibling();
      for (; l; ) {
        const a = l;
        l = l.getNextSibling(), s.append(a);
      }
      r.insertAfter(t), t.insertAfter(s);
    }
    const i = this.__key;
    let o = 0;
    if (e && (S(t) || Oe(139), o = t.getChildrenSize(), t.splice(o, 0, this.getChildren())), e && S(t)) {
      const s = M();
      if (w(s)) for (const l of s.getStartEndPoints()) l.key === i && l.type === "element" && l.set(t.getKey(), o + l.offset, "element");
    }
    return this.remove(), r.getChildrenSize() === 0 && r.remove(), t;
  }
  insertAfter(t, e = !0) {
    const r = this.getParentOrThrow();
    if (K(r) || Oe(39), q(t)) return super.insertAfter(t, e);
    const i = this.getNextSiblings();
    if (r.insertAfter(t, e), i.length !== 0) {
      const o = At(r);
      i.forEach((s) => o.append(s)), t.insertAfter(o, e);
    }
    return t;
  }
  remove(t) {
    const e = this.getPreviousSibling(), r = this.getNextSibling();
    super.remove(t), e && r && Vt(e) && Vt(r) && (dl(e.getFirstChild(), r.getFirstChild()), r.remove());
  }
  resetOnCopyNodeFrom(t) {
    super.resetOnCopyNodeFrom(t), t.getChecked() && this.setChecked(!1);
  }
  insertNewAfter(t, e = !0) {
    const r = At(this);
    return this.insertAfter(r, e), r;
  }
  collapseAtStart(t) {
    if (Vt(this)) return !1;
    const e = this.getParentOrThrow();
    if (q(e.getParentOrThrow())) return Qa(this), !0;
    const r = U().append(...this.getChildren()), i = this.getNextSiblings();
    if (i.length > 0) {
      const o = At(e);
      o.append(...i), e.insertAfter(o);
    }
    return e.insertAfter(r), this.remove(), e.getChildrenSize() === 0 && e.remove(), r.selectStart(), !0;
  }
  getValue() {
    return this.getLatest().__value;
  }
  setValue(t) {
    const e = this.getWritable();
    return e.__value = t, e;
  }
  getChecked() {
    const t = this.getLatest();
    let e;
    const r = this.getParent();
    return K(r) && (e = r.getListType()), e === "check" ? !!t.__checked : void 0;
  }
  setChecked(t) {
    const e = this.getWritable();
    return e.__checked = t, e;
  }
  toggleChecked() {
    const t = this.getWritable();
    return t.setChecked(!t.__checked);
  }
  getIndent() {
    const t = this.getParent();
    if (t === null || !this.isAttached()) return this.getLatest().__indent;
    let e = t.getParentOrThrow(), r = 0;
    for (; q(e); ) e = e.getParentOrThrow().getParentOrThrow(), r++;
    return r;
  }
  setIndent(t) {
    typeof t != "number" && Oe(117), (t = Math.floor(t)) >= 0 || Oe(199);
    let e = this.getIndent();
    for (; e !== t; ) e < t ? (e_(this), e++) : (Qa(this), e--);
    return this;
  }
  canInsertAfter(t) {
    return q(t);
  }
  canReplaceWith(t) {
    return q(t);
  }
  canMergeWith(t) {
    return q(t) || to(t);
  }
  extractWithChild(t, e) {
    if (!w(e)) return !1;
    const r = e.anchor.getNode(), i = e.focus.getNode();
    return this.isParentOf(r) && this.isParentOf(i) && this.getTextContent().length === e.getTextContent().length;
  }
  isParentRequired() {
    return !0;
  }
  createParentElementNode() {
    return ce("bullet");
  }
  canMergeWhenEmpty() {
    return !0;
  }
}
function r_(n) {
  if (n.classList.contains("task-list-item")) {
    for (const r of n.children) if (r.tagName === "INPUT") return tc(r);
  }
  if (n.classList.contains("joplin-checkbox")) {
    for (const r of n.children) if (r.classList.contains("checkbox-wrapper") && r.children.length > 0 && r.children[0].tagName === "INPUT") return tc(r.children[0]);
  }
  const t = n.getAttribute("aria-checked"), e = ke(t === "true" || t !== "false" && void 0);
  return fe(e, n), { after: fd.bind(null, e), node: ee(e, n) };
}
function tc(n) {
  if (n.getAttribute("type") !== "checkbox") return { node: null };
  const t = ke(n.hasAttribute("checked"));
  return { after: fd.bind(null, t), node: t };
}
function fd(n, t) {
  const e = t[0];
  return t.length === 1 && to(e) && !n.getFormatType() && e.getFormatType() ? (n.setFormat(e.getFormatType()), e.getChildren()) : t;
}
function ke(n) {
  return Mt(new Hr(void 0, n));
}
function q(n) {
  return n instanceof Hr;
}
class hl extends Nt {
  __tag;
  __start;
  __listType;
  $config() {
    return this.config("list", { $transform: (t) => {
      (function(e) {
        const r = e.getNextSibling();
        K(r) && e.getListType() === r.getListType() && dl(e, r);
      })(t), ud(t);
    }, extends: Nt, importDOM: { ol: () => ({ conversion: nc, priority: 0 }), ul: () => ({ conversion: nc, priority: 0 }) } });
  }
  constructor(t = "number", e = 1, r) {
    super(r);
    const i = i_[t] || t;
    this.__listType = i, this.__tag = i === "number" ? "ol" : "ul", this.__start = e;
  }
  afterCloneFrom(t) {
    super.afterCloneFrom(t), this.__listType = t.__listType, this.__tag = t.__tag, this.__start = t.__start;
  }
  getTag() {
    return this.getLatest().__tag;
  }
  setListType(t) {
    const e = this.getWritable();
    return e.__listType = t, e.__tag = t === "number" ? "ol" : "ul", e;
  }
  getListType() {
    return this.getLatest().__listType;
  }
  getStart() {
    return this.getLatest().__start;
  }
  setStart(t) {
    const e = this.getWritable();
    return e.__start = t, e;
  }
  createDOM(t, e) {
    const r = this.__tag, i = V().createElement(r);
    return this.__start !== 1 && i.setAttribute("start", String(this.__start)), i.__lexicalListType = this.__listType, ec(i, t.theme, this), i;
  }
  updateDOM(t, e, r) {
    return t.__tag !== this.__tag || t.__listType !== this.__listType || (ec(e, r.theme, this), t.__start !== this.__start && e.setAttribute("start", String(this.__start)), !1);
  }
  updateFromJSON(t) {
    return super.updateFromJSON(t).setListType(t.listType).setStart(t.start);
  }
  exportDOM(t) {
    const e = this.createDOM(t._config, t);
    return F(e) && (this.__start !== 1 && e.setAttribute("start", String(this.__start)), this.__listType === "check" && e.setAttribute("__lexicalListType", "check")), { element: e };
  }
  exportJSON() {
    return { ...super.exportJSON(), listType: this.getListType(), start: this.getStart(), tag: this.getTag() };
  }
  canBeEmpty() {
    return !1;
  }
  canIndent() {
    return !1;
  }
  splice(t, e, r) {
    let i = r;
    for (let o = 0; o < r.length; o++) {
      const s = r[o];
      q(s) || (i === r && (i = [...r]), i[o] = this.createListItemNode().append(!S(s) || K(s) || s.isInline() ? s : mt(s.getTextContent())));
    }
    return super.splice(t, e, i);
  }
  extractWithChild(t) {
    return q(t);
  }
  createListItemNode() {
    return ke();
  }
}
function ec(n, t, e) {
  const r = [], i = [], o = t.list;
  if (o !== void 0) {
    const s = o[`${e.__tag}Depth`] || [], l = Qp(e) - 1, a = l % s.length, c = s[a], u = o[e.__tag];
    let f;
    const d = o.nested, h = o.checklist;
    if (d !== void 0 && d.list && (f = d.list), u !== void 0 && r.push(u), h !== void 0 && e.__listType === "check" && r.push(h), c !== void 0) {
      r.push(...Ce(c));
      for (let _ = 0; _ < s.length; _++) _ !== a && i.push(e.__tag + _);
    }
    if (f !== void 0) {
      const _ = Ce(f);
      l > 1 ? r.push(..._) : i.push(..._);
    }
  }
  i.length > 0 && Pn(n, ...i), r.length > 0 && Lt(n, ...r);
}
function nc(n) {
  let t;
  if ((function(e) {
    return F(e) && e.nodeName.toLowerCase() === "ol";
  })(n)) {
    const e = n.start;
    t = ce("number", e);
  } else t = (function(e) {
    if (e.getAttribute("__lexicallisttype") === "check" || e.classList.contains("contains-task-list") || e.getAttribute("data-is-checklist") === "1") return !0;
    for (const r of e.childNodes) if (F(r) && r.hasAttribute("aria-checked")) return !0;
    return !1;
  })(n) ? ce("check") : ce("bullet");
  return ee(t, n), { after: (e) => (function(r, i) {
    const o = i.createListItemNode.bind(i), s = [];
    for (let l = 0; l < r.length; l++) {
      const a = r[l];
      if (q(a)) {
        s.push(a);
        const c = a.getChildren();
        c.length > 1 && c.forEach((u) => {
          K(u) && s.push(o().append(u));
        });
      } else s.push(o().append(a));
    }
    return s;
  })(e, t), node: t };
}
const i_ = { ol: "number", ul: "bullet" };
function ce(n = "number", t = 1) {
  return Mt(new hl(n, t));
}
function K(n) {
  return n instanceof hl;
}
function o_(n) {
  const t = [];
  for (const e of n) if (q(e)) {
    t.push(e);
    const r = e.getChildren();
    if (r.length > 1) for (const i of r) K(i) && t.push(ke().append(i));
  } else t.push(ke().append(e));
  return t;
}
const s_ = { $import: (n, t) => {
  let e;
  var r;
  return Jf(t, "ol") ? e = ce("number", t.start) : e = (r = t).matches('[__lexicallisttype="check"], .contains-task-list, [data-is-checklist="1"]') || r.querySelector(":scope > [aria-checked]") !== null ? ce("check") : ce("bullet"), ee(e, t), [e.splice(0, 0, cl(o_(n.$importChildren(t)), t))];
}, match: Bt.tag("ol", "ul"), name: "@lexical/list/list" };
function dd(n, t) {
  if (t.length !== 1) return t;
  const e = t[0];
  return to(e) && !n.getFormatType() && e.getFormatType() ? (n.setFormat(e.getFormatType()), e.getChildren()) : t;
}
function hd(n) {
  const t = (s) => al(s) && !K(s);
  if (!n.some(t)) return n;
  const e = [];
  let r = [];
  const i = () => {
    r.length > 0 && (e.push(r), r = []);
  };
  for (const s of n) t(s) ? (i(), e.push(S(s) ? s.getChildren() : [s])) : r.push(s);
  i();
  const o = [];
  for (const s of e) o.length > 0 && o.push(Ke()), o.push(...s);
  return o;
}
const l_ = { $import: (n, t) => {
  const e = t.getAttribute("aria-checked"), r = ke(e === "true" || e !== "false" && void 0);
  return fe(r, t), ee(r, t), [r.splice(0, 0, hd(dd(r, n.$importChildren(t))))];
}, match: Bt.tag("li"), name: "@lexical/list/li" };
function rc(n, t, e) {
  const r = Jf(e, "input") ? e : e.querySelector('input[type="checkbox"]');
  if (!r || r.getAttribute("type") !== "checkbox") return [];
  const i = ke(r.hasAttribute("checked"));
  return fe(i, t), ee(i, t), [i.splice(0, 0, hd(dd(i, n.$importChildren(t))))];
}
Bt.tag("li").classAll("task-list-item"), Bt.tag("li").classAll("joplin-checkbox");
const a_ = /* @__PURE__ */ $("UPDATE_LIST_START_COMMAND"), gd = /* @__PURE__ */ $("INSERT_UNORDERED_LIST_COMMAND"), pd = /* @__PURE__ */ $("INSERT_ORDERED_LIST_COMMAND"), c_ = /* @__PURE__ */ $("REMOVE_LIST_COMMAND");
function u_(n, t) {
  return an(n.registerCommand(pd, () => (Ya("number"), !0), ar), n.registerCommand(a_, (e) => {
    const { listNodeKey: r, newStart: i } = e, o = Z(r);
    return !!K(o) && (o.getListType() === "number" && (o.setStart(i), ud(o)), !0);
  }, ar), n.registerCommand(gd, () => (Ya("bullet"), !0), ar), n.registerCommand(c_, () => (t_(), !0), ar), n.registerCommand(wr, () => n_(!1), ar), n.registerCommand(Os, (e) => {
    if ((function() {
      const s = M();
      if (!w(s) || !s.isCollapsed() || s.anchor.offset !== 0) return !1;
      const l = s.anchor.getNode(), a = dt(l, q);
      if (!q(a)) return !1;
      const c = a.getFirstDescendant();
      if (c === null || !a.is(l) && !c.is(l)) return !1;
      const u = a.getParent();
      if (!K(u) || !a.is(u.getFirstChild())) return !1;
      const f = u.getPreviousSibling();
      if (!W(f) || f.isIsolated() || !f.isKeyboardSelectable() && f.isInline()) return !1;
      const d = U().append(...a.getChildren());
      return u.insertBefore(d), a.remove(), u.isEmpty() && u.remove(), d.selectStart(), !0;
    })()) return e.preventDefault(), !0;
    const r = M();
    if (!w(r) || !r.isCollapsed()) return !1;
    const { anchor: i } = r;
    if (i.offset !== 0) return !1;
    let o = i.getNode();
    for (; !q(o); ) {
      if (o.getPreviousSibling() !== null) return !1;
      const s = o.getParent();
      if (s === null) return !1;
      o = s;
    }
    return !(!q(o) || !o.collapseAtStart(r)) && (e.preventDefault(), !0);
  }, Rh), n.registerNodeTransform(Hr, (e) => {
    const r = e.getFirstChild();
    if (r) {
      if (O(r)) {
        const i = r.getStyle(), o = r.getFormat();
        e.getTextStyle() !== i && e.setTextStyle(i), e.getTextFormat() !== o && e.setTextFormat(o);
      }
    } else {
      const i = M();
      w(i) && (i.style !== e.getTextStyle() || i.format !== e.getTextFormat()) && i.isCollapsed() && e.is(i.anchor.getNode()) && e.setTextStyle(i.style).setTextFormat(i.format);
    }
  }), n.registerNodeTransform(We, (e) => {
    const r = e.getParent();
    if (q(r) && e.is(r.getFirstChild())) {
      const i = e.getStyle(), o = e.getFormat();
      i === r.getTextStyle() && o === r.getTextFormat() || r.setTextStyle(i).setTextFormat(o);
    }
  }));
}
const ic = /* @__PURE__ */ new Set(["http:", "https:", "mailto:", "sms:", "tel:"]);
class uo extends Nt {
  __url;
  __target;
  __rel;
  __title;
  static getType() {
    return "link";
  }
  static clone(t) {
    return new uo(t.__url, { rel: t.__rel, target: t.__target, title: t.__title }, t.__key);
  }
  constructor(t = "", e = {}, r) {
    super(r);
    const { target: i = null, rel: o = null, title: s = null } = e;
    this.__url = t, this.__target = i, this.__rel = o, this.__title = s;
  }
  afterCloneFrom(t) {
    super.afterCloneFrom(t), this.__url = t.__url, this.__rel = t.__rel, this.__target = t.__target, this.__title = t.__title;
  }
  createDOM(t) {
    const e = V().createElement("a");
    return this.updateLinkDOM(null, e, t), Lt(e, t.theme.link), e;
  }
  updateLinkDOM(t, e, r) {
    if (hf(e)) {
      t && t.__url === this.__url || (e.href = this.sanitizeUrl(this.__url));
      for (const i of ["target", "rel", "title"]) {
        const o = `__${i}`, s = this[o];
        t && t[o] === s || (s ? e[i] = s : e.removeAttribute(i));
      }
    }
  }
  updateDOM(t, e, r) {
    return this.updateLinkDOM(t, e, r), !1;
  }
  static importDOM() {
    return { a: (t) => ({ conversion: f_, priority: 1 }) };
  }
  static importJSON(t) {
    return gl().updateFromJSON(t);
  }
  updateFromJSON(t) {
    return super.updateFromJSON(t).setURL(t.url).setRel(t.rel || null).setTarget(t.target || null).setTitle(t.title || null);
  }
  sanitizeUrl(t) {
    const e = t;
    t = sc(t);
    try {
      const r = new URL(sc(t));
      if (!ic.has(r.protocol)) return "about:blank";
    } catch {
      const i = e.replace(/[\u0000-\u001F\u007F\s]/g, "").match(/^([a-z][a-z0-9+.-]*):/i);
      if (i != null && !ic.has(`${i[1].toLowerCase()}:`)) return "about:blank";
    }
    return t;
  }
  exportJSON() {
    return { ...super.exportJSON(), rel: this.getRel(), target: this.getTarget(), title: this.getTitle(), url: this.getURL() };
  }
  getURL() {
    return this.getLatest().__url;
  }
  setURL(t) {
    const e = this.getWritable();
    return e.__url = t, e;
  }
  getTarget() {
    return this.getLatest().__target;
  }
  setTarget(t) {
    const e = this.getWritable();
    return e.__target = t, e;
  }
  getRel() {
    return this.getLatest().__rel;
  }
  setRel(t) {
    const e = this.getWritable();
    return e.__rel = t, e;
  }
  getTitle() {
    return this.getLatest().__title;
  }
  setTitle(t) {
    const e = this.getWritable();
    return e.__title = t, e;
  }
  insertNewAfter(t, e = !0) {
    const r = At(this);
    return this.insertAfter(r, e), r;
  }
  canInsertTextBefore() {
    return !1;
  }
  canInsertTextAfter() {
    return !1;
  }
  canBeEmpty() {
    return !1;
  }
  isInline() {
    return !0;
  }
  extractWithChild(t, e, r) {
    if (!w(e)) return !1;
    const i = e.anchor.getNode(), o = e.focus.getNode();
    return (this.is(i) || this.isParentOf(i)) && (this.is(o) || this.isParentOf(o)) && e.getTextContent().length > 0;
  }
  isEmailURI() {
    return this.__url.startsWith("mailto:");
  }
  isWebSiteURI() {
    return this.__url.startsWith("https://") || this.__url.startsWith("http://");
  }
  shouldMergeAdjacentLink(t) {
    return this.getType() === t.getType() && this.__url === t.__url && this.__target === t.__target && this.__rel === t.__rel && this.__title === t.__title;
  }
}
function f_(n) {
  let t = null;
  if (hf(n)) {
    const e = n.textContent;
    (e !== null && e !== "" || n.children.length > 0) && (t = gl(n.getAttribute("href") || "", { rel: n.getAttribute("rel"), target: n.getAttribute("target"), title: n.getAttribute("title") }));
  }
  return { node: t };
}
function gl(n = "", t) {
  return Mt(new uo(n, t));
}
const oc = /* @__PURE__ */ $("TOGGLE_LINK_COMMAND"), d_ = /^\+?[0-9\s()-]{5,}$/;
function sc(n) {
  return n.match(/^[a-z][a-z0-9+.-]*:/i) || n.match(/^[/#.]/) ? n : n.includes("@") ? `mailto:${n}` : d_.test(n) ? `tel:${n}` : `https://${n}`;
}
Bt.tag("a");
const Rn = /^(\d+(?:\.\d+)?)px$/, et = { BOTH: 3, COLUMN: 2, NO_STATUS: 0, ROW: 1 };
class Jr extends Nt {
  __colSpan;
  __rowSpan;
  __headerState;
  __width;
  __backgroundColor;
  __verticalAlign;
  static getType() {
    return "tablecell";
  }
  static clone(t) {
    return new Jr(t.__headerState, t.__colSpan, t.__width, t.__key);
  }
  afterCloneFrom(t) {
    super.afterCloneFrom(t), this.__rowSpan = t.__rowSpan, this.__backgroundColor = t.__backgroundColor, this.__verticalAlign = t.__verticalAlign, this.__colSpan = t.__colSpan, this.__headerState = t.__headerState, this.__width = t.__width;
  }
  static importDOM() {
    return { td: (t) => ({ conversion: lc, priority: 0 }), th: (t) => ({ conversion: lc, priority: 0 }) };
  }
  static importJSON(t) {
    return jr().updateFromJSON(t);
  }
  updateFromJSON(t) {
    return super.updateFromJSON(t).setHeaderStyles(t.headerState).setColSpan(t.colSpan || 1).setRowSpan(t.rowSpan || 1).setWidth(t.width || void 0).setBackgroundColor(t.backgroundColor || null).setVerticalAlign(t.verticalAlign || void 0);
  }
  constructor(t = et.NO_STATUS, e = 1, r, i) {
    super(i), this.__colSpan = e, this.__rowSpan = 1, this.__headerState = t, this.__width = r, this.__backgroundColor = null, this.__verticalAlign = void 0;
  }
  createDOM(t) {
    const e = V().createElement(this.getTag());
    return this.__width && (e.style.width = `${this.__width}px`), this.__colSpan > 1 && (e.colSpan = this.__colSpan), this.__rowSpan > 1 && (e.rowSpan = this.__rowSpan), this.__backgroundColor !== null && (e.style.backgroundColor = this.__backgroundColor), Ts(this.__verticalAlign) && (e.style.verticalAlign = this.__verticalAlign), Lt(e, t.theme.tableCell, this.hasHeader() && t.theme.tableCellHeader), e;
  }
  exportDOM(t) {
    const e = super.exportDOM(t);
    if (F(e.element)) {
      const r = e.element;
      r.setAttribute("data-temporary-table-cell-lexical-key", this.getKey()), r.style.border = "1px solid black", this.__colSpan > 1 && (r.colSpan = this.__colSpan), this.__rowSpan > 1 && (r.rowSpan = this.__rowSpan), r.style.width = `${this.getWidth() || 75}px`, r.style.verticalAlign = this.getVerticalAlign() || "top", r.style.textAlign = "start", this.__backgroundColor === null && this.hasHeader() && (r.style.backgroundColor = "#f2f3f5");
    }
    return e;
  }
  exportJSON() {
    return { ...super.exportJSON(), ...Ts(this.__verticalAlign) && { verticalAlign: this.__verticalAlign }, backgroundColor: this.getBackgroundColor(), colSpan: this.__colSpan, headerState: this.__headerState, rowSpan: this.__rowSpan, width: this.getWidth() };
  }
  getColSpan() {
    return this.getLatest().__colSpan;
  }
  setColSpan(t) {
    const e = this.getWritable();
    return e.__colSpan = t, e;
  }
  getRowSpan() {
    return this.getLatest().__rowSpan;
  }
  setRowSpan(t) {
    const e = this.getWritable();
    return e.__rowSpan = t, e;
  }
  getTag() {
    return this.hasHeader() ? "th" : "td";
  }
  setHeaderStyles(t, e = et.BOTH) {
    const r = this.getWritable();
    return r.__headerState = t & e | r.__headerState & ~e, r;
  }
  getHeaderStyles() {
    return this.getLatest().__headerState;
  }
  setWidth(t) {
    const e = this.getWritable();
    return e.__width = t, e;
  }
  getWidth() {
    return this.getLatest().__width;
  }
  getBackgroundColor() {
    return this.getLatest().__backgroundColor;
  }
  setBackgroundColor(t) {
    const e = this.getWritable();
    return e.__backgroundColor = t, e;
  }
  getVerticalAlign() {
    return this.getLatest().__verticalAlign;
  }
  setVerticalAlign(t) {
    const e = this.getWritable();
    return e.__verticalAlign = t || void 0, e;
  }
  toggleHeaderStyle(t) {
    const e = this.getWritable();
    return (e.__headerState & t) === t ? e.__headerState -= t : e.__headerState += t, e;
  }
  hasHeaderState(t) {
    return (this.getHeaderStyles() & t) === t;
  }
  hasHeader() {
    return this.getLatest().__headerState !== et.NO_STATUS;
  }
  updateDOM(t) {
    return t.__headerState !== this.__headerState || t.__width !== this.__width || t.__colSpan !== this.__colSpan || t.__rowSpan !== this.__rowSpan || t.__backgroundColor !== this.__backgroundColor || t.__verticalAlign !== this.__verticalAlign;
  }
  isShadowRoot() {
    return !0;
  }
  collapseAtStart() {
    return !0;
  }
  canBeEmpty() {
    return !1;
  }
  canIndent() {
    return !1;
  }
}
function Ts(n) {
  return n === "middle" || n === "bottom";
}
function lc(n) {
  const t = n, e = n.nodeName.toLowerCase();
  let r;
  Rn.test(t.style.width) && (r = parseFloat(t.style.width));
  let i = et.NO_STATUS;
  if (e === "th") {
    const m = t.getAttribute("scope");
    if (m === "col") i = et.COLUMN;
    else if (m === "row") i = et.ROW;
    else {
      const p = t.parentElement, g = F(p) && p.nodeName.toLowerCase() === "tr" && F(p.parentElement) && (p.parentElement.nodeName.toLowerCase() === "thead" || p.rowIndex === 0), y = t.cellIndex === 0;
      g && (i |= et.ROW), y && (i |= et.COLUMN), i === et.NO_STATUS && (i = et.ROW);
    }
  }
  const o = jr(i, t.colSpan, r);
  o.__rowSpan = t.rowSpan;
  const s = t.style.backgroundColor;
  s !== "" && (o.__backgroundColor = s);
  const l = t.style.verticalAlign;
  Ts(l) && (o.__verticalAlign = l);
  const a = t.style, c = (a && a.textDecoration || "").split(" "), u = a.fontWeight === "700" || a.fontWeight === "bold", f = c.includes("line-through"), d = a.fontStyle === "italic", h = c.includes("underline"), _ = a.color;
  return { after: (m) => {
    const p = [];
    let g = null;
    const y = () => {
      if (g) {
        const x = g.getFirstChild();
        Pt(x) && g.getChildrenSize() === 1 && x.remove();
      }
    };
    for (const x of m) if (Js(x) || O(x) || Pt(x)) {
      if (O(x) && (u && x.toggleFormat("bold"), f && x.toggleFormat("strikethrough"), d && x.toggleFormat("italic"), h && x.toggleFormat("underline"), _)) {
        const v = x.getStyle();
        v.includes("color:") || x.setStyle(v + `color: ${_};`);
      }
      g ? g.append(x) : (g = U().append(x), p.push(g));
    } else p.push(x), y(), g = null;
    return y(), p.length === 0 && p.push(U()), p;
  }, node: o };
}
function jr(n = et.NO_STATUS, t = 1, e) {
  return Mt(new Jr(n, t, e));
}
function ze(n) {
  return n instanceof Jr;
}
function Vn(n, ...t) {
  const e = new URL("https://lexical.dev/docs/error"), r = new URLSearchParams();
  r.append("code", n);
  for (const i of t) r.append("v", i);
  throw e.search = r.toString(), Error(`Minified Lexical error #${n}; visit ${e.toString()} for the full message or use the non-minified dev environment for full errors and additional helpful warnings.`);
}
class qr extends Nt {
  __height;
  static getType() {
    return "tablerow";
  }
  static clone(t) {
    return new qr(t.__height, t.__key);
  }
  afterCloneFrom(t) {
    super.afterCloneFrom(t), this.__height = t.__height;
  }
  static importDOM() {
    return { tr: (t) => ({ conversion: h_, priority: 0 }) };
  }
  static importJSON(t) {
    return fo().updateFromJSON(t);
  }
  updateFromJSON(t) {
    return super.updateFromJSON(t).setHeight(t.height);
  }
  constructor(t, e) {
    super(e), this.__height = t;
  }
  exportJSON() {
    const t = this.getHeight();
    return { ...super.exportJSON(), ...t === void 0 ? void 0 : { height: t } };
  }
  createDOM(t) {
    const e = V().createElement("tr");
    return this.__height && (e.style.height = `${this.__height}px`), Lt(e, t.theme.tableRow), e;
  }
  extractWithChild(t, e, r) {
    return r === "html";
  }
  isShadowRoot() {
    return !0;
  }
  setHeight(t) {
    const e = this.getWritable();
    return e.__height = t, e;
  }
  getHeight() {
    return this.getLatest().__height;
  }
  updateDOM(t) {
    return t.__height !== this.__height;
  }
  canBeEmpty() {
    return !1;
  }
  canIndent() {
    return !1;
  }
}
function h_(n) {
  const t = n;
  let e;
  return Rn.test(t.style.height) && (e = parseFloat(t.style.height)), { after: (r) => Bi(r, ze), node: fo(e) };
}
function fo(n) {
  return Mt(new qr(n));
}
function rr(n) {
  return n instanceof qr;
}
function g_(n) {
  const t = dt(n, (e) => gc(e));
  if (gc(t)) return t;
  throw new Error("Expected table cell to be inside of table.");
}
function _d(n, t) {
  const e = g_(n), { x: r, y: i } = e.getCordsFromCellNode(n, t);
  return { above: e.getCellNodeFromCords(r, i - 1, t), below: e.getCellNodeFromCords(r, i + 1, t), left: e.getCellNodeFromCords(r - 1, i, t), right: e.getCellNodeFromCords(r + 1, i, t) };
}
function ac(n, t, e = !0, r, i) {
  const o = n.getChildren();
  if (t >= o.length || t < 0) throw new Error("Table row target index out of range");
  const s = o[t];
  if (!rr(s)) throw new Error("Row before insertion index does not exist.");
  for (let l = 0; l < r; l++) {
    const a = s.getChildren(), c = a.length, u = fo();
    for (let f = 0; f < c; f++) {
      const d = a[f];
      ze(d) || Vn(12);
      const { above: h, below: _ } = _d(d, i);
      let m = et.NO_STATUS;
      const p = h && h.getWidth() || _ && _.getWidth() || void 0;
      (h && h.hasHeaderState(et.COLUMN) || _ && _.hasHeaderState(et.COLUMN)) && (m |= et.COLUMN);
      const g = jr(m, 1, p);
      g.append(U()), u.append(g);
    }
    e ? s.insertAfter(u) : s.insertBefore(u);
  }
  return n;
}
function cc(n, t, e = !0, r, i) {
  const o = n.getChildren(), s = [];
  for (let l = 0; l < o.length; l++) {
    const a = o[l];
    if (rr(a)) for (let c = 0; c < r; c++) {
      const u = a.getChildren();
      if (t >= u.length || t < 0) throw new Error("Table column target index out of range");
      const f = u[t];
      ze(f) || Vn(12);
      const { left: d, right: h } = _d(f, i);
      let _ = et.NO_STATUS;
      (d && d.hasHeaderState(et.ROW) || h && h.hasHeaderState(et.ROW)) && (_ |= et.ROW);
      const m = jr(_);
      m.append(U()), s.push({ newTableCell: m, targetCell: f });
    }
  }
  return s.forEach(({ newTableCell: l, targetCell: a }) => {
    e ? a.insertAfter(l) : a.insertBefore(l);
  }), n;
}
function p_(n, t, e) {
  const r = [];
  let i = null, o = null;
  function s(a) {
    let c = r[a];
    return c === void 0 && (r[a] = c = []), c;
  }
  const l = n.getChildren();
  for (let a = 0; a < l.length; a++) {
    const c = l[a];
    rr(c) || Vn(209);
    const u = s(a);
    for (let f = c.getFirstChild(), d = 0; f != null; f = f.getNextSibling()) {
      for (ze(f) || Vn(147); u[d] !== void 0; ) d++;
      const h = { cell: f, startColumn: d, startRow: a }, { __rowSpan: _, __colSpan: m } = f;
      for (let p = 0; p < _ && !(a + p >= l.length); p++) {
        const g = s(a + p);
        for (let y = 0; y < m; y++) g[d + y] = h;
      }
    }
  }
  return [r, i, o];
}
function qe(n) {
  return F(n) && n.nodeName === "TABLE";
}
function uc(n, t) {
  if (!t) return t;
  const e = qe(t) ? t : t.querySelector("table");
  return qe(e) || Vn(341, n.constructor.name, n.getType(), n.getKey(), t.nodeName), e;
}
function __(n, t) {
  for (let e = t, r = null; e !== null; e = e.getParent()) {
    if (n.is(e)) return r;
    ze(e) && (r = e);
  }
  return null;
}
function m_(n, t, e) {
  return __(n, ve(t, e));
}
function fc(n, t, e) {
  const r = n.querySelector("colgroup");
  if (!r) return;
  const i = [];
  for (let o = 0; o < t; o++) {
    const s = V().createElement("col"), l = e && e[o];
    l && (s.style.width = `${l}px`), i.push(s);
  }
  r.replaceChildren(...i);
}
function dc(n, t, e) {
  if (!t.theme.tableAlignment) return;
  const r = [], i = [];
  for (const o of ["center", "right"]) {
    const s = t.theme.tableAlignment[o];
    s && (o === e ? i : r).push(s);
  }
  Pn(n, ...r), Lt(n, ...i);
}
const y_ = /* @__PURE__ */ new WeakSet();
function hc(n = st()) {
  return y_.has(n);
}
class Vr extends Nt {
  __rowStriping;
  __frozenColumnCount;
  __frozenRowCount;
  __colWidths;
  static getType() {
    return "table";
  }
  getColWidths() {
    return this.getLatest().__colWidths;
  }
  setColWidths(t) {
    const e = this.getWritable();
    return e.__colWidths = t, e;
  }
  static clone(t) {
    return new Vr(t.__key);
  }
  afterCloneFrom(t) {
    super.afterCloneFrom(t), this.__colWidths = t.__colWidths, this.__rowStriping = t.__rowStriping, this.__frozenColumnCount = t.__frozenColumnCount, this.__frozenRowCount = t.__frozenRowCount;
  }
  static importDOM() {
    return { table: (t) => ({ conversion: x_, priority: 1 }) };
  }
  static importJSON(t) {
    return pl().updateFromJSON(t);
  }
  updateFromJSON(t) {
    return super.updateFromJSON(t).setRowStriping(t.rowStriping || !1).setFrozenColumns(t.frozenColumnCount || 0).setFrozenRows(t.frozenRowCount || 0).setColWidths(t.colWidths);
  }
  constructor(t) {
    super(t), this.__rowStriping = !1, this.__frozenColumnCount = 0, this.__frozenRowCount = 0, this.__colWidths = void 0;
  }
  exportJSON() {
    return { ...super.exportJSON(), colWidths: this.getColWidths(), frozenColumnCount: this.__frozenColumnCount ? this.__frozenColumnCount : void 0, frozenRowCount: this.__frozenRowCount ? this.__frozenRowCount : void 0, rowStriping: this.__rowStriping ? this.__rowStriping : void 0 };
  }
  extractWithChild(t, e, r) {
    return r === "html";
  }
  getDOMSlot(t) {
    const e = qe(t) ? t : t.querySelector("table");
    return qe(e) || Vn(229), super.getDOMSlot(t).withElement(e).withAfter(e.querySelector("colgroup"));
  }
  createDOM(t, e) {
    const r = V().createElement("table");
    this.__style && Or(r.style, this.__style);
    const i = V().createElement("colgroup");
    if (r.appendChild(i), pf(i), Lt(r, t.theme.table), this.updateTableElement(null, r, t), hc(e)) {
      const o = V().createElement("div"), s = t.theme.tableScrollableWrapper;
      return s ? Lt(o, s) : o.style.overflowX = "auto", o.appendChild(r), this.updateTableWrapper(null, o, r, t), o;
    }
    return r;
  }
  updateTableWrapper(t, e, r, i) {
    this.__frozenColumnCount !== (t ? t.__frozenColumnCount : 0) && (function(o, s, l, a) {
      a > 0 ? (Lt(o, l.theme.tableFrozenColumn), s.setAttribute("data-lexical-frozen-column", "true")) : (Pn(o, l.theme.tableFrozenColumn), s.removeAttribute("data-lexical-frozen-column"));
    })(e, r, i, this.__frozenColumnCount), this.__frozenRowCount !== (t ? t.__frozenRowCount : 0) && (function(o, s, l, a) {
      a > 0 ? (Lt(o, l.theme.tableFrozenRow), s.setAttribute("data-lexical-frozen-row", "true")) : (Pn(o, l.theme.tableFrozenRow), s.removeAttribute("data-lexical-frozen-row"));
    })(e, r, i, this.__frozenRowCount);
  }
  updateTableElement(t, e, r) {
    this.__style !== (t ? t.__style : "") && Or(e.style, this.__style, t ? t.__style : ""), this.__rowStriping !== (!!t && t.__rowStriping) && (function(s, l, a) {
      a ? (Lt(s, l.theme.tableRowStriping), s.setAttribute("data-lexical-row-striping", "true")) : (Pn(s, l.theme.tableRowStriping), s.removeAttribute("data-lexical-row-striping"));
    })(e, r, this.__rowStriping);
    const i = t ? t.getColumnCount() : 0, o = t ? t.__colWidths : void 0;
    this.getColumnCount() === i && this.getColWidths() === o || fc(e, this.getColumnCount(), this.getColWidths()), dc(e, r, this.getFormatType());
  }
  updateDOM(t, e, r) {
    const i = uc(this, e);
    return e === i === hc() || (F(o = e) && o.nodeName === "DIV" && this.updateTableWrapper(t, e, i, r), this.updateTableElement(t, i, r), !1);
    var o;
  }
  scaleDOMColWidths(t, e) {
    const r = this.getColWidths();
    r && fc(uc(this, t), this.getColumnCount(), r.map((i) => i * e));
  }
  exportDOM(t) {
    const e = super.exportDOM(t), { element: r } = e;
    return { after: (i) => {
      if (e.after && (i = e.after(i)), !qe(i) && F(i) && (i = i.querySelector("table")), !qe(i)) return null;
      dc(i, t._config, this.getFormatType());
      const [o] = p_(this), s = /* @__PURE__ */ new Map();
      for (const u of o) for (const f of u) {
        const d = f.cell.getKey();
        s.has(d) || s.set(d, { colSpan: f.cell.getColSpan(), startColumn: f.startColumn });
      }
      const l = /* @__PURE__ */ new Set();
      for (const u of i.querySelectorAll(":scope > tr > [data-temporary-table-cell-lexical-key]")) {
        const f = u.getAttribute("data-temporary-table-cell-lexical-key");
        if (f) {
          const d = s.get(f);
          if (u.removeAttribute("data-temporary-table-cell-lexical-key"), d) {
            s.delete(f);
            for (let h = 0; h < d.colSpan; h++) l.add(h + d.startColumn);
          }
        }
      }
      const a = i.querySelector(":scope > colgroup");
      if (a) {
        const u = Array.from(i.querySelectorAll(":scope > colgroup > col")).filter((f, d) => l.has(d));
        a.replaceChildren(...u);
      }
      const c = i.querySelectorAll(":scope > tr");
      if (c.length > 0) {
        const u = V().createElement("tbody");
        for (const f of c) u.appendChild(f);
        i.append(u);
      }
      return i;
    }, element: !qe(r) && F(r) ? r.querySelector("table") : r };
  }
  canBeEmpty() {
    return !1;
  }
  isShadowRoot() {
    return !0;
  }
  getCordsFromCellNode(t, e) {
    const { rows: r, domRows: i } = e;
    for (let o = 0; o < r; o++) {
      const s = i[o];
      if (s != null) for (let l = 0; l < s.length; l++) {
        const a = s[l];
        if (a == null) continue;
        const { elem: c } = a, u = m_(this, c);
        if (u !== null && t.is(u)) return { x: l, y: o };
      }
    }
    throw new Error("Cell not found in table.");
  }
  getDOMCellFromCords(t, e, r) {
    const { domRows: i } = r, o = i[e];
    if (o == null) return null;
    const s = o[t < o.length ? t : o.length - 1];
    return s ?? null;
  }
  getDOMCellFromCordsOrThrow(t, e, r) {
    const i = this.getDOMCellFromCords(t, e, r);
    if (!i) throw new Error("Cell not found at cords.");
    return i;
  }
  getCellNodeFromCords(t, e, r) {
    const i = this.getDOMCellFromCords(t, e, r);
    if (i == null) return null;
    const o = ve(i.elem);
    return ze(o) ? o : null;
  }
  getCellNodeFromCordsOrThrow(t, e, r) {
    const i = this.getCellNodeFromCords(t, e, r);
    if (!i) throw new Error("Node at cords not TableCellNode.");
    return i;
  }
  getRowStriping() {
    return !!this.getLatest().__rowStriping;
  }
  setRowStriping(t) {
    const e = this.getWritable();
    return e.__rowStriping = t, e;
  }
  setFrozenColumns(t) {
    const e = this.getWritable();
    return e.__frozenColumnCount = t, e;
  }
  getFrozenColumns() {
    return this.getLatest().__frozenColumnCount;
  }
  setFrozenRows(t) {
    const e = this.getWritable();
    return e.__frozenRowCount = t, e;
  }
  getFrozenRows() {
    return this.getLatest().__frozenRowCount;
  }
  canSelectBefore() {
    return !0;
  }
  canIndent() {
    return !1;
  }
  getColumnCount() {
    const t = this.getFirstChild();
    if (!rr(t)) return 0;
    let e = 0;
    return t.getChildren().forEach((r) => {
      ze(r) && (e += r.getColSpan());
    }), e;
  }
}
function x_(n) {
  const t = pl();
  n.hasAttribute("data-lexical-row-striping") && t.setRowStriping(!0), n.hasAttribute("data-lexical-frozen-column") && t.setFrozenColumns(1), n.hasAttribute("data-lexical-frozen-row") && t.setFrozenRows(1);
  const e = n.querySelector(":scope > colgroup");
  if (e) {
    let r = [];
    for (const i of e.querySelectorAll(":scope > col")) {
      let o = i.style.width || "";
      if (!Rn.test(o) && (o = i.getAttribute("width") || "", !/^\d+$/.test(o))) {
        r = void 0;
        break;
      }
      r.push(parseFloat(o));
    }
    r && t.setColWidths(r);
  }
  return { after: (r) => Bi(r, rr), node: t };
}
function pl() {
  return Mt(new Vr());
}
function gc(n) {
  return n instanceof Vr;
}
Bt.tag("table"), Bt.tag("tr"), Bt.tag("td", "th");
class _l {
  constructor(t = {}) {
    if (new.target === _l) throw new TypeError("EditorInterface is abstract");
    this.options = t;
  }
  mount() {
    throw new Error("Not implemented");
  }
  destroy() {
    throw new Error("Not implemented");
  }
  getHTML() {
    throw new Error("Not implemented");
  }
  setHTML() {
    throw new Error("Not implemented");
  }
  insertHTML() {
    throw new Error("Not implemented");
  }
  command() {
    throw new Error("Not implemented");
  }
}
const S_ = /* @__PURE__ */ new Set([
  "SCRIPT",
  "STYLE",
  "OBJECT",
  "EMBED",
  "LINK",
  "META",
  "BASE",
  "FORM",
  "INPUT",
  "BUTTON",
  "TEXTAREA",
  "SELECT",
  "OPTION",
  "TEMPLATE",
  "SVG",
  "MATH"
]), C_ = /* @__PURE__ */ new Set([
  "class",
  "title",
  "lang",
  "dir",
  "style",
  "data-align",
  "aria-label"
]), v_ = {
  A: /* @__PURE__ */ new Set(["href", "target", "rel"]),
  DIV: /* @__PURE__ */ new Set(["width", "height"]),
  FIGURE: /* @__PURE__ */ new Set(["width", "height"]),
  IMG: /* @__PURE__ */ new Set(["src", "alt", "width", "height", "loading"]),
  VIDEO: /* @__PURE__ */ new Set([
    "src",
    "controls",
    "preload",
    "poster",
    "width",
    "height",
    "muted",
    "loop",
    "playsinline"
  ]),
  SOURCE: /* @__PURE__ */ new Set(["src", "type"]),
  IFRAME: /* @__PURE__ */ new Set([
    "src",
    "title",
    "width",
    "height",
    "allow",
    "allowfullscreen",
    "loading",
    "referrerpolicy",
    "sandbox"
  ]),
  OL: /* @__PURE__ */ new Set(["start", "reversed", "type"]),
  LI: /* @__PURE__ */ new Set(["value"]),
  TH: /* @__PURE__ */ new Set(["colspan", "rowspan", "scope"]),
  TD: /* @__PURE__ */ new Set(["colspan", "rowspan"])
}, b_ = /* @__PURE__ */ new Set([
  "color",
  "background-color",
  "text-align",
  "width",
  "height",
  "max-width",
  "min-width",
  "margin",
  "margin-left",
  "margin-right",
  "float",
  "display",
  "aspect-ratio"
]), T_ = /* @__PURE__ */ new Set([
  "youtube.com",
  "www.youtube.com",
  "youtube-nocookie.com",
  "www.youtube-nocookie.com",
  "player.vimeo.com"
]);
function w_(n) {
  return String(n ?? "").split(";").map((t) => t.trim()).filter(Boolean).map((t) => t.split(":")).filter((t) => t.length >= 2).map(([t, ...e]) => [
    t.trim().toLowerCase(),
    e.join(":").trim()
  ]).filter(([t, e]) => b_.has(t) && !/url\s*\(|expression\s*\(|javascript:|vbscript:|behavior\s*:|-moz-binding|@import/i.test(e) && /^[#(),.%\sa-z0-9+\-/*]+$/i.test(e)).map(([t, e]) => `${t}: ${e}`).join("; ");
}
function k_(n, t = "", e = "") {
  const r = String(n ?? "").trim();
  return !r || /[\u0000-\u001f\u007f]/.test(r) ? "" : r.startsWith("/") && !r.startsWith("//") || r.startsWith("#") || /^https?:\/\//i.test(r) || e === "href" && /^(?:mailto|tel):/i.test(r) || t === "IMG" && e === "src" && /^data:image\/(?:png|jpeg|gif|webp);base64,/i.test(r) ? r : "";
}
function _n(n, t = {}) {
  const e = String(n || "").toUpperCase(), r = v_[e] ?? /* @__PURE__ */ new Set(), i = {};
  for (const [o, s] of Object.entries(t)) {
    const l = String(o).toLowerCase(), a = String(s ?? "").trim();
    if (!(l.startsWith("on") || !C_.has(l) && !r.has(l))) {
      if (["href", "src", "poster"].includes(l)) {
        const c = k_(a, e, l);
        c && (i[l] = c);
        continue;
      }
      if (l === "style") {
        const c = w_(a);
        c && (i.style = c);
        continue;
      }
      if (l === "class") {
        const c = a.split(/\s+/).filter((u) => /^[A-Za-z0-9_-]+$/.test(u)).filter((u) => ![
          "erased-block",
          "is-selected",
          "is-dragging",
          "erased-image-node--selected",
          "erased-video-node--selected"
        ].includes(u));
        c.length && (i.class = c.join(" "));
        continue;
      }
      i[l] = a;
    }
  }
  return i;
}
function zn(n) {
  const e = new window.DOMParser().parseFromString(
    `<!doctype html><html><body>${String(n ?? "")}</body></html>`,
    "text/html"
  ).body;
  return e.querySelectorAll("*").forEach((r) => {
    if (S_.has(r.tagName)) {
      r.remove();
      return;
    }
    const i = Object.fromEntries(
      Array.from(r.attributes).map(({ name: s, value: l }) => [s, l])
    ), o = _n(r.tagName, i);
    if (Array.from(r.attributes).forEach(({ name: s }) => {
      r.removeAttribute(s);
    }), Object.entries(o).forEach(([s, l]) => {
      r.setAttribute(s, l);
    }), r.tagName === "IFRAME") {
      const s = r.getAttribute("src") || "";
      let l = "";
      try {
        l = new URL(
          s,
          window.location?.origin ?? "http://localhost"
        ).hostname.toLowerCase();
      } catch {
        l = "";
      }
      if (!T_.has(l)) {
        r.remove();
        return;
      }
      r.setAttribute(
        "sandbox",
        "allow-scripts allow-same-origin allow-presentation"
      ), r.setAttribute("referrerpolicy", "strict-origin-when-cross-origin"), r.setAttribute("loading", "lazy");
    }
    r.tagName === "A" && r.getAttribute("target")?.toLowerCase() === "_blank" && r.setAttribute("rel", "noopener noreferrer");
  }), e.innerHTML;
}
function pc(n) {
  return Object.fromEntries(
    Array.from(n.attributes).map(({ name: t, value: e }) => [
      t,
      e
    ])
  );
}
function _c(n) {
  const t = Number.parseInt(String(n ?? ""), 10);
  return Number.isFinite(t) && t > 0 ? t : null;
}
function N_(n, t, e) {
  const r = String(n ?? "").split(";").map((i) => i.trim()).filter(Boolean).filter((i) => {
    const o = i.split(":", 1)[0]?.trim().toLowerCase();
    return o !== "width" && o !== "height" && o !== "max-width";
  });
  return t && r.push(`width: ${t}px`), e && r.push(`height: ${e}px`), r.push("max-width: 100%"), `${r.join("; ")};`;
}
function mc(n) {
  const t = document.createElement("img"), e = _n("IMG", n);
  for (const [r, i] of Object.entries(e))
    t.setAttribute(r, String(i));
  return t.setAttribute("draggable", "false"), t.setAttribute("contenteditable", "false"), t;
}
function E_(n, t, e, r, i = "south-east") {
  if (!(n instanceof PointerEvent))
    return;
  n.preventDefault(), n.stopPropagation();
  const o = n.clientX, s = e.getBoundingClientRect().width || e.width || 300, l = e.getBoundingClientRect().height || e.height || 200, a = l > 0 ? s / l : 1.5, c = t.closest(".erased-lexical-editor")?.getBoundingClientRect().width ?? 800, u = 80, f = Math.max(u, c - 30), d = i.includes("west"), h = n.currentTarget;
  if (h instanceof HTMLElement)
    try {
      h.setPointerCapture(n.pointerId);
    } catch {
    }
  t.classList.add("erased-image-node--resizing");
  const _ = (p) => {
    p.preventDefault(), p.stopPropagation();
    const g = d ? o - p.clientX : p.clientX - o, y = Math.round(
      Math.min(
        f,
        Math.max(u, s + g)
      )
    ), x = Math.round(y / a);
    e.style.width = `${y}px`, e.style.height = `${x}px`, e.style.maxWidth = "100%", t.style.width = `${y}px`, t.style.height = `${x}px`, t.dataset.resizeWidth = String(y), t.dataset.resizeHeight = String(x);
    const v = t.querySelector(".erased-image-size-badge");
    v && (v.textContent = `${y} × ${x} px`);
  }, m = (p) => {
    p?.preventDefault(), p?.stopPropagation(), window.removeEventListener("pointermove", _, !0), window.removeEventListener("pointerup", m, !0), window.removeEventListener("pointercancel", m, !0), t.classList.remove("erased-image-node--resizing");
    const g = _c(t.dataset.resizeWidth), y = _c(t.dataset.resizeHeight);
    delete t.dataset.resizeWidth, delete t.dataset.resizeHeight, !(!g || !y) && t.dispatchEvent(
      new CustomEvent("erased-image-resize", {
        bubbles: !0,
        detail: {
          nodeKey: r,
          width: g,
          height: y
        }
      })
    );
  };
  window.addEventListener("pointermove", _, { capture: !0, passive: !1 }), window.addEventListener("pointerup", m, { capture: !0, passive: !1 }), window.addEventListener("pointercancel", m, { capture: !0, passive: !1 });
}
function ci(n, t, e, r) {
  const i = document.createElement("span");
  return i.className = `erased-image-node__resize-handle erased-image-node__resize-handle--${n}`, i.setAttribute("role", "presentation"), i.setAttribute("aria-hidden", "true"), i.addEventListener(
    "pointerdown",
    (o) => E_(o, t, e, r, n)
  ), i;
}
class Gr extends Yn {
  __attributes;
  static getType() {
    return "image";
  }
  static clone(t) {
    return new Gr(
      { ...t.__attributes },
      t.__key
    );
  }
  static importJSON(t) {
    return $o(
      t.attributes ?? {}
    );
  }
  static importDOM() {
    return {
      img: () => ({
        conversion: (t) => ({
          node: $o(pc(t))
        }),
        priority: 5
      }),
      figure: () => ({
        conversion: (t) => {
          const e = t.querySelector("img");
          if (!e) return null;
          const r = pc(e), i = t.getAttribute("class") || "";
          return i.includes("media-left") ? r["data-align"] = "left" : i.includes("media-right") ? r["data-align"] = "right" : i.includes("media-center") && (r["data-align"] = "center"), {
            node: $o(r)
          };
        },
        priority: 5
      })
    };
  }
  constructor(t = {}, e) {
    super(e), this.__attributes = _n("IMG", t);
  }
  createDOM() {
    const t = document.createElement("span"), e = mc(this.__attributes), r = this.getKey();
    t.className = "erased-image-node", t.dataset.lexicalImage = "true", t.dataset.nodeKey = r, this.__attributes["data-align"] && (t.dataset.align = this.__attributes["data-align"]), this.__attributes.width && (t.style.width = `${this.__attributes.width}px`), this.__attributes.height && (t.style.height = `${this.__attributes.height}px`), t.contentEditable = "false";
    const i = () => {
      const s = e.getBoundingClientRect(), l = t.querySelector(".erased-image-size-badge");
      l && (l.textContent = `${Math.round(s.width || e.width || 300)} × ${Math.round(s.height || e.height || 200)} px`);
    };
    t.addEventListener("click", (s) => {
      s.preventDefault(), s.stopPropagation(), t.closest(".erased-lexical-editor")?.querySelectorAll(".erased-image-node--selected").forEach((a) => {
        a !== t && a.classList.remove("erased-image-node--selected");
      }), t.classList.add("erased-image-node--selected"), i();
    });
    const o = document.createElement("span");
    return o.className = "erased-image-size-badge", o.textContent = "Resizing…", t.append(
      e,
      o,
      ci("north-west", t, e, r),
      ci("north-east", t, e, r),
      ci("south-west", t, e, r),
      ci("south-east", t, e, r)
    ), t;
  }
  updateDOM(t, e) {
    const r = t.__attributes["data-align"], i = this.__attributes["data-align"];
    r !== i && (i ? e.dataset.align = i : delete e.dataset.align);
    const o = t.__attributes.width, s = this.__attributes.width;
    o !== s && s && (e.style.width = `${s}px`);
    const l = t.__attributes.height, a = this.__attributes.height;
    return l !== a && a && (e.style.height = `${a}px`), JSON.stringify(t.__attributes) !== JSON.stringify(this.__attributes);
  }
  decorate() {
    return null;
  }
  setDimensions(t, e) {
    const r = this.getWritable();
    return r.__attributes = {
      ...r.__attributes,
      width: String(t),
      height: String(e),
      style: N_(
        r.__attributes.style,
        t,
        e
      )
    }, r;
  }
  setAlignment(t) {
    const e = this.getWritable();
    return e.__attributes = {
      ...e.__attributes,
      "data-align": t
    }, e;
  }
  exportDOM() {
    const t = mc(this.__attributes), e = this.__attributes["data-align"];
    if (e) {
      const r = document.createElement("figure");
      return r.className = `media-${e}`, r.appendChild(t), { element: r };
    }
    return {
      element: t
    };
  }
  exportJSON() {
    return {
      ...super.exportJSON(),
      type: "image",
      version: 1,
      attributes: { ...this.__attributes }
    };
  }
}
function $o(n = {}) {
  return Mt(
    new Gr(n)
  );
}
function yc(n) {
  return n instanceof Gr;
}
function O_(n) {
  return Object.fromEntries(
    Array.from(n.attributes).map(({ name: t, value: e }) => [t, e])
  );
}
function Fo(n, t, e) {
  const r = document.createElement(n.toLowerCase()), i = _n(n, t);
  for (const [o, s] of Object.entries(i))
    r.setAttribute(o, String(s));
  return n !== "HR" && n !== "IMG" && (r.innerHTML = zn(e)), r.dataset.lexicalRawHtml = "true", r.contentEditable = "false", r;
}
function A_(n) {
  return n.classList.contains("video-embed") || n.querySelector("iframe, video") !== null;
}
function bn(n) {
  return {
    node: md(
      n.tagName,
      _n(n.tagName, O_(n)),
      zn(n.innerHTML)
    )
  };
}
class ho extends Yn {
  __tag;
  __attributes;
  __innerHTML;
  static getType() {
    return "raw-html";
  }
  static clone(t) {
    return new ho(
      t.__tag,
      { ...t.__attributes },
      t.__innerHTML,
      t.__key
    );
  }
  static importJSON(t) {
    return md(
      t.tag,
      t.attributes ?? {},
      t.innerHTML ?? ""
    );
  }
  static importDOM() {
    return {
      div: (t) => A_(t) ? {
        conversion: bn,
        priority: 4
      } : null,
      hr: () => ({
        conversion: bn,
        priority: 4
      }),
      iframe: () => ({
        conversion: bn,
        priority: 4
      }),
      video: () => ({
        conversion: bn,
        priority: 4
      }),
      img: () => ({
        conversion: bn,
        priority: 4
      }),
      figure: () => ({
        conversion: bn,
        priority: 4
      })
    };
  }
  constructor(t, e = {}, r = "", i) {
    super(i), this.__tag = String(t || "DIV").toUpperCase(), this.__attributes = _n(this.__tag, e), this.__innerHTML = zn(r);
  }
  createDOM() {
    return Fo(
      this.__tag,
      this.__attributes,
      this.__innerHTML
    );
  }
  updateDOM(t, e) {
    if (t.__tag !== this.__tag)
      return !0;
    const r = Fo(
      this.__tag,
      this.__attributes,
      this.__innerHTML
    );
    return e.replaceWith(r), !1;
  }
  decorate() {
    return null;
  }
  exportDOM() {
    const t = Fo(
      this.__tag,
      this.__attributes,
      this.__innerHTML
    );
    return t.removeAttribute("data-lexical-raw-html"), t.removeAttribute("contenteditable"), { element: t };
  }
  exportJSON() {
    return {
      ...super.exportJSON(),
      type: "raw-html",
      version: 1,
      tag: this.__tag,
      attributes: { ...this.__attributes },
      innerHTML: this.__innerHTML
    };
  }
}
function md(n, t = {}, e = "") {
  return Mt(
    new ho(n, t, e)
  );
}
function xc(n) {
  return Object.fromEntries(
    Array.from(n.attributes).map(({ name: t, value: e }) => [t, e])
  );
}
function Sc(n) {
  const t = Number.parseInt(String(n ?? ""), 10);
  return Number.isFinite(t) && t > 0 ? t : null;
}
function M_(n, t, e) {
  const r = String(n ?? "").split(";").map((i) => i.trim()).filter(Boolean).filter((i) => {
    const o = i.split(":", 1)[0]?.trim().toLowerCase();
    return o !== "width" && o !== "height" && o !== "max-width";
  });
  return t && r.push(`width: ${t}px`), e && r.push(`height: ${e}px`), r.push("max-width: 100%"), `${r.join("; ")};`;
}
function D_(n, t, e, r, i = "south-east") {
  if (!(n instanceof PointerEvent))
    return;
  n.preventDefault(), n.stopPropagation();
  const o = n.clientX, s = e.getBoundingClientRect().width || 560, l = e.getBoundingClientRect().height || 315, a = l > 0 ? s / l : 16 / 9, c = t.closest(".erased-lexical-editor")?.getBoundingClientRect().width ?? 800, u = 160, f = Math.max(u, c - 20), d = i.includes("west"), h = n.currentTarget;
  if (h instanceof HTMLElement)
    try {
      h.setPointerCapture(n.pointerId);
    } catch {
    }
  t.classList.add("erased-video-node--resizing");
  const _ = (p) => {
    p.preventDefault(), p.stopPropagation();
    const g = d ? o - p.clientX : p.clientX - o, y = Math.round(
      Math.min(
        f,
        Math.max(u, s + g)
      )
    ), x = Math.round(y / a);
    t.style.width = `${y}px`, t.style.height = `${x}px`, e.style.width = `${y}px`, e.style.height = `${x}px`, t.dataset.resizeWidth = String(y), t.dataset.resizeHeight = String(x);
    const v = t.querySelector(".erased-image-size-badge");
    v && (v.textContent = `${y} × ${x} px`);
  }, m = (p) => {
    p?.preventDefault(), p?.stopPropagation(), window.removeEventListener("pointermove", _, !0), window.removeEventListener("pointerup", m, !0), window.removeEventListener("pointercancel", m, !0), t.classList.remove("erased-video-node--resizing");
    const g = Sc(t.dataset.resizeWidth), y = Sc(t.dataset.resizeHeight);
    delete t.dataset.resizeWidth, delete t.dataset.resizeHeight, !(!g || !y) && t.dispatchEvent(
      new CustomEvent("erased-video-resize", {
        bubbles: !0,
        detail: { nodeKey: r, width: g, height: y }
      })
    );
  };
  window.addEventListener("pointermove", _, { capture: !0, passive: !1 }), window.addEventListener("pointerup", m, { capture: !0, passive: !1 }), window.addEventListener("pointercancel", m, { capture: !0, passive: !1 });
}
function ui(n, t, e, r) {
  const i = document.createElement("span");
  return i.className = `erased-image-node__resize-handle erased-image-node__resize-handle--${n}`, i.setAttribute("role", "presentation"), i.setAttribute("aria-hidden", "true"), i.addEventListener(
    "pointerdown",
    (o) => D_(o, t, e, r, n)
  ), i;
}
class Xr extends Yn {
  __tag;
  __attributes;
  __innerHTML;
  static getType() {
    return "video-embed-node";
  }
  static clone(t) {
    return new Xr(
      t.__tag,
      { ...t.__attributes },
      t.__innerHTML,
      t.__key
    );
  }
  static importJSON(t) {
    return Po(
      t.tag,
      t.attributes ?? {},
      t.innerHTML ?? ""
    );
  }
  static importDOM() {
    return {
      div: (t) => {
        if (!t.classList.contains("video-embed") && !t.querySelector("iframe, video"))
          return null;
        const e = xc(t);
        return {
          conversion: () => ({
            node: Po(t.tagName, e, t.innerHTML)
          }),
          priority: 6
        };
      },
      figure: (t) => {
        if (!t.classList.contains("post-video") && !t.querySelector("video, iframe"))
          return null;
        const e = xc(t), r = t.getAttribute("class") || "";
        return r.includes("media-left") ? e["data-align"] = "left" : r.includes("media-right") ? e["data-align"] = "right" : r.includes("media-center") && (e["data-align"] = "center"), {
          conversion: () => ({
            node: Po("FIGURE", e, t.innerHTML)
          }),
          priority: 6
        };
      }
    };
  }
  constructor(t = "DIV", e = {}, r = "", i) {
    super(i), this.__tag = String(t || "DIV").toUpperCase(), this.__attributes = _n(this.__tag, e), this.__innerHTML = zn(r);
  }
  createDOM() {
    const t = document.createElement("div"), e = this.getKey();
    t.className = "erased-video-node video-embed", t.dataset.lexicalVideo = "true", t.dataset.nodeKey = e, this.__attributes["data-align"] && (t.dataset.align = this.__attributes["data-align"]), this.__attributes.width && (t.style.width = `${this.__attributes.width}px`), this.__attributes.height && (t.style.height = `${this.__attributes.height}px`), t.contentEditable = "false";
    const r = document.createElement("div");
    r.className = "erased-video-node__inner", r.innerHTML = zn(this.__innerHTML);
    const i = () => {
      const s = t.getBoundingClientRect(), l = t.querySelector(".erased-image-size-badge");
      l && (l.textContent = `${Math.round(s.width || 560)} × ${Math.round(s.height || 315)} px`);
    };
    t.addEventListener("click", (s) => {
      s.preventDefault(), s.stopPropagation(), t.closest(".erased-lexical-editor")?.querySelectorAll(".erased-video-node--selected, .erased-image-node--selected").forEach((a) => {
        a !== t && a.classList.remove("erased-video-node--selected", "erased-image-node--selected");
      }), t.classList.add("erased-video-node--selected"), i();
    });
    const o = document.createElement("span");
    return o.className = "erased-image-size-badge", o.textContent = "Resizing video…", t.append(
      r,
      o,
      ui("north-west", t, t, e),
      ui("north-east", t, t, e),
      ui("south-west", t, t, e),
      ui("south-east", t, t, e)
    ), t;
  }
  updateDOM(t, e) {
    const r = t.__attributes["data-align"], i = this.__attributes["data-align"];
    r !== i && (i ? e.dataset.align = i : delete e.dataset.align);
    const o = t.__attributes.width, s = this.__attributes.width;
    o !== s && s && (e.style.width = `${s}px`);
    const l = t.__attributes.height, a = this.__attributes.height;
    return l !== a && a && (e.style.height = `${a}px`), JSON.stringify(t.__attributes) !== JSON.stringify(this.__attributes) || t.__innerHTML !== this.__innerHTML;
  }
  decorate() {
    return null;
  }
  setDimensions(t, e) {
    const r = this.getWritable();
    return r.__attributes = {
      ...r.__attributes,
      width: String(t),
      height: String(e),
      style: M_(r.__attributes.style, t, e)
    }, r;
  }
  setAlignment(t) {
    const e = this.getWritable();
    return e.__attributes = {
      ...e.__attributes,
      "data-align": t
    }, e;
  }
  exportDOM() {
    const t = document.createElement(this.__tag === "FIGURE" ? "figure" : "div");
    t.className = this.__tag === "FIGURE" ? "post-video" : "video-embed";
    for (const [r, i] of Object.entries(this.__attributes))
      t.setAttribute(r, String(i));
    const e = this.__attributes["data-align"];
    return e && (t.dataset.align = e, this.__tag === "FIGURE" && (t.className = `post-video media-${e}`)), t.innerHTML = zn(this.__innerHTML), { element: t };
  }
  exportJSON() {
    return {
      ...super.exportJSON(),
      type: "video-embed-node",
      version: 1,
      tag: this.__tag,
      attributes: { ...this.__attributes },
      innerHTML: this.__innerHTML
    };
  }
}
function Po(n = "DIV", t = {}, e = "") {
  return Mt(new Xr(n, t, e));
}
function Cc(n) {
  return n instanceof Xr;
}
function L_() {
  return window.erasedEditor && typeof window.erasedEditor.command == "function" ? window.erasedEditor : null;
}
function dr(n, t = null) {
  const e = L_();
  if (!e) return;
  const r = e.options?.element || document.querySelector("#visual-editor");
  r?.focus(), e.command(n, t), r?.focus();
}
function vc() {
  const n = document.querySelector("#lexical-toolbar");
  !n || n.dataset.initialized === "true" || (n.dataset.initialized = "true", n.addEventListener("mousedown", (t) => {
    t.target.closest("button") && t.preventDefault();
  }), n.addEventListener("click", (t) => {
    const e = t.target.closest("button");
    if (!e) return;
    const r = e.dataset.lexicalCommand;
    if (r) {
      dr(r);
      return;
    }
    if (e.dataset.lexicalLink !== void 0) {
      const i = window.prompt("Enter link URL:", "https://");
      i?.trim() && dr("createLink", i.trim());
    }
  }), n.addEventListener("change", (t) => {
    const e = t.target.closest("select");
    if (e) {
      e.dataset.lexicalFormatBlock !== void 0 ? e.value && dr("formatBlock", e.value) : e.dataset.lexicalAlign !== void 0 && e.value && (dr(e.value), e.value = "");
      return;
    }
    const r = t.target.closest("input[data-lexical-color]");
    if (r) {
      const i = r.dataset.lexicalColor;
      dr(i, r.value);
    }
  }));
}
function $_(n) {
  const t = document.querySelector("#lexical-toolbar");
  t && n.read(() => {
    const e = M();
    if (!w(e)) return;
    const r = e.hasFormat("bold"), i = e.hasFormat("italic"), o = e.hasFormat("underline"), s = e.hasFormat("strikethrough"), l = e.hasFormat("subscript"), a = e.hasFormat("superscript"), c = e.hasFormat("code");
    Je(t, "bold", r), Je(t, "italic", i), Je(t, "underline", o), Je(t, "strikeThrough", s), Je(t, "subscript", l), Je(t, "superscript", a), Je(t, "code", c);
    const u = e.anchor.getNode(), f = u.getKey() === "root" ? u : u.getTopLevelElementOrThrow(), d = t.querySelector("select[data-lexical-format-block]");
    d && (jp(f) ? d.value = f.getTag() : f.getType() === "quote" ? d.value = "blockquote" : d.value = "p");
    const h = !!u.findMatchingParent((m) => m.getType() === "table"), _ = t.querySelector(".lexical-playground-toolbar__group--table");
    _ && (_.style.display = h ? "inline-flex" : "none");
  });
}
function Je(n, t, e) {
  const r = n.querySelector(`button[data-lexical-command="${t}"]`);
  r && (r.classList.toggle("is-active", e), r.setAttribute("aria-pressed", e ? "true" : "false"));
}
document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", vc) : vc();
class F_ extends _l {
  mount() {
    const t = this.options.element;
    if (!(t instanceof HTMLElement))
      throw new TypeError("LexicalAdapter requires a valid element.");
    this.editor = Xu({
      namespace: "ERASED-CMS",
      nodes: [
        fl,
        od,
        hl,
        Hr,
        uo,
        Vr,
        qr,
        Jr,
        Gr,
        Xr,
        ho
      ],
      theme: {
        paragraph: "erased-editor__paragraph",
        quote: "erased-editor__quote",
        heading: {
          h1: "erased-editor__heading erased-editor__heading--h1",
          h2: "erased-editor__heading erased-editor__heading--h2",
          h3: "erased-editor__heading erased-editor__heading--h3",
          h4: "erased-editor__heading erased-editor__heading--h4"
        },
        list: {
          ul: "erased-editor__list erased-editor__list--bullet",
          ol: "erased-editor__list erased-editor__list--number",
          listitem: "erased-editor__list-item",
          nested: {
            listitem: "erased-editor__list-item--nested"
          }
        },
        link: "erased-editor__link",
        table: "erased-editor__table",
        tableCell: "erased-editor__table-cell",
        tableCellHeader: "erased-editor__table-cell--header",
        tableRow: "erased-editor__table-row",
        text: {
          bold: "erased-editor__text--bold",
          italic: "erased-editor__text--italic",
          underline: "erased-editor__text--underline",
          strikethrough: "erased-editor__text--strike",
          subscript: "erased-editor__text--subscript",
          superscript: "erased-editor__text--superscript",
          code: "erased-editor__text--code"
        }
      },
      onError(r) {
        console.error("ERASED Lexical editor error:", r);
      }
    }), t.innerHTML = "", t.classList.add("erased-lexical-editor"), t.setAttribute("contenteditable", "true"), t.setAttribute("role", "textbox"), t.setAttribute("aria-multiline", "true"), t.setAttribute("spellcheck", "true"), this.editor.setRootElement(t);
    const e = Zp();
    return this.unregister = an(
      Vp(this.editor),
      Yp(this.editor, e, 300),
      u_(this.editor),
      this.editor.registerUpdateListener(({ editorState: r }) => {
        $_(r), r.read(() => {
          this.options.onUpdate?.(
            ys(this.editor)
          );
        });
      })
    ), this.setupDragAndDrop(t), this.setupMediaEvents(t), this.setHTML(this.options.content ?? ""), this;
  }
  setupDragAndDrop(t) {
    ["dragenter", "dragover"].forEach((e) => {
      t.addEventListener(e, (r) => {
        r.preventDefault(), r.stopPropagation(), t.classList.add("erased-lexical-editor--drop-active");
      });
    }), ["dragleave", "drop"].forEach((e) => {
      t.addEventListener(e, (r) => {
        r.preventDefault(), r.stopPropagation(), t.classList.remove("erased-lexical-editor--drop-active");
      });
    }), t.addEventListener("drop", (e) => {
      e.preventDefault(), e.stopPropagation();
      const r = e.dataTransfer?.files;
      if (!r || !r.length) return;
      const i = document.querySelector("input[name=csrf]")?.value || "";
      Array.from(r).forEach((o) => {
        if (!o.type.startsWith("image/") && !o.type.startsWith("video/")) return;
        const s = new FormData();
        s.append("csrf", i), s.append("file", o), fetch("/admin/media/upload", { method: "POST", body: s }).then((l) => l.json()).then((l) => {
          if (!l.ok) throw new Error(l.error || "Upload failed");
          if (l.type === "video")
            this.insertHTML(`<figure class="post-video" data-align="center"><video controls preload="metadata"><source src="${l.url}"></video></figure><p><br></p>`);
          else {
            const a = (l.alt || "").replace(/"/g, "&quot;");
            this.insertHTML(`<figure class="media-large media-center"><img src="${l.url}" alt="${a}"><figcaption contenteditable="true"></figcaption></figure><p><br></p>`);
          }
        }).catch((l) => alert(`Upload error: ${l.message}`));
      });
    });
  }
  setupMediaEvents(t) {
    const e = (o, s) => {
      const l = o.detail ?? {}, a = Number.parseInt(l.width, 10), c = Number.parseInt(l.height, 10), u = String(l.nodeKey ?? "");
      !u || a < 1 || c < 1 || !this.editor || this.editor.update(() => {
        const f = Z(u);
        s(f) && f.setDimensions(a, c);
      });
    }, r = (o) => e(o, yc), i = (o) => e(o, Cc);
    t.addEventListener("erased-image-resize", r), t.addEventListener("erased-video-resize", i), this.mediaEventCleanup = () => {
      t.removeEventListener("erased-image-resize", r), t.removeEventListener("erased-video-resize", i);
    };
  }
  destroy() {
    this.unregister?.(), this.unregister = null, this.mediaEventCleanup?.(), this.mediaEventCleanup = null, this.editor && (this.editor.setRootElement(null), this.editor = null);
  }
  getHTML() {
    if (!this.editor)
      return "";
    let t = "";
    return this.editor.getEditorState().read(() => {
      t = ys(this.editor);
    }), t;
  }
  setHTML(t) {
    if (!this.editor)
      return this;
    this.editor.update(() => {
      const r = pt();
      if (r.clear(), t && String(t).trim() !== "")
        try {
          const i = new DOMParser().parseFromString(
            String(t),
            "text/html"
          ), o = ms(
            this.editor,
            i
          );
          r.append(...o);
        } catch (i) {
          console.error("Error importing DOM into Lexical:", i);
        }
      r.isEmpty() && r.append(U());
    });
    const e = document.querySelector("#visual-editor");
    return e && (e.scrollTop = 0), this;
  }
  insertHTML(t) {
    return !this.editor || !t ? this : (this.editor.update(() => {
      const e = new DOMParser().parseFromString(
        String(t),
        "text/html"
      ), r = ms(
        this.editor,
        e
      );
      if (r.length === 0) {
        console.warn(
          "Lexical generated no nodes from inserted HTML:",
          t
        );
        return;
      }
      let i = M();
      (!i || !w(i)) && (i = pt().selectEnd()), i.insertNodes(r);
    }), this.editor.focus(), this);
  }
  command(t, e = null) {
    if (!this.editor)
      return !1;
    this.editor.focus();
    const r = {
      undo: () => this.editor.dispatchCommand(Vi, void 0),
      redo: () => this.editor.dispatchCommand(Gi, void 0),
      bold: () => this.editor.dispatchCommand(Ot, "bold"),
      italic: () => this.editor.dispatchCommand(Ot, "italic"),
      underline: () => this.editor.dispatchCommand(Ot, "underline"),
      strikeThrough: () => this.editor.dispatchCommand(Ot, "strikethrough"),
      subscript: () => this.editor.dispatchCommand(Ot, "subscript"),
      superscript: () => this.editor.dispatchCommand(Ot, "superscript"),
      code: () => this.editor.dispatchCommand(Ot, "code"),
      alignLeft: () => this.formatAlignment("left"),
      alignCenter: () => this.formatAlignment("center"),
      alignRight: () => this.formatAlignment("right"),
      alignJustify: () => this.formatAlignment("justify"),
      insertUnorderedList: () => this.editor.dispatchCommand(gd, void 0),
      insertOrderedList: () => this.editor.dispatchCommand(pd, void 0),
      createLink: () => this.editor.dispatchCommand(oc, String(e ?? "")),
      unlink: () => this.editor.dispatchCommand(oc, null),
      removeFormat: () => this.removeFormat(),
      insertImage: () => {
        const a = typeof e == "object" ? e : { src: e, alt: "" }, c = (a.alt || "").replace(/"/g, "&quot;");
        return this.insertHTML(`<figure class="media-large media-center"><img src="${a.src || ""}" alt="${c}"><figcaption contenteditable="true"></figcaption></figure><p><br></p>`), !0;
      },
      insertRowAbove: () => this.editor.update(() => ac(!1)),
      insertRowBelow: () => this.editor.update(() => ac(!0)),
      insertColumnLeft: () => this.editor.update(() => cc(!1)),
      insertColumnRight: () => this.editor.update(() => cc(!0)),
      deleteRow: () => this.deleteRow(),
      deleteColumn: () => this.deleteColumn(),
      deleteTable: () => this.deleteSelectedTable(),
      setTableCellBackground: () => this.setTableCellBackground(e)
    };
    if (t === "formatBlock")
      return this.formatBlock(e);
    const i = r[t];
    if (!i)
      return console.warn(`Unsupported Lexical command: ${t}`), !1;
    const o = document.querySelector("#visual-editor"), s = o ? o.scrollTop : 0, l = i();
    return o && (o.scrollTop = s, requestAnimationFrame(() => {
      o.scrollTop = s;
    })), l;
  }
  deleteRow() {
    return this.editor.update(() => {
      const t = M();
      if (w(t)) {
        const r = t.anchor.getNode().findMatchingParent((i) => i.getType() === "tablerow");
        r && r.remove();
      }
    }), !0;
  }
  deleteColumn() {
    return this.editor.update(() => {
      const t = M();
      if (w(t)) {
        const e = t.anchor.getNode(), r = e.findMatchingParent((o) => o.getType() === "tablecell"), i = e.findMatchingParent((o) => o.getType() === "tablerow");
        if (r && i) {
          const o = i.getChildren().indexOf(r), s = e.findMatchingParent((l) => l.getType() === "table");
          s && o !== -1 && s.getChildren().forEach((l) => {
            const a = l.getChildren();
            a[o] && a[o].remove();
          });
        }
      }
    }), !0;
  }
  deleteSelectedTable() {
    return this.editor.update(() => {
      const t = M();
      if (w(t)) {
        const i = t.anchor.getNode().findMatchingParent((o) => o.getType() === "table");
        if (i) {
          i.remove();
          return;
        }
      }
      const e = document.querySelector(".visual-editor table.is-selected");
      e && e.remove();
    }), !0;
  }
  setTableCellBackground(t) {
    if (!t) return !1;
    const e = document.activeElement?.closest("td, th") || document.querySelector(".visual-editor td.is-selected, .visual-editor th.is-selected");
    return e ? e.style.backgroundColor = t : this.editor.update(() => {
      const r = M();
      if (w(r)) {
        const o = r.anchor.getNode().findMatchingParent((s) => s.getType() === "tablecell");
        o && o.setBackgroundColor(t);
      }
    }), !0;
  }
  removeFormat() {
    return this.editor ? (this.editor.update(() => {
      const t = M();
      w(t) && t.getNodes().forEach((e) => {
        O(e) && (e.setFormat(0), e.setStyle(""));
      });
    }), this.editor.focus(), !0) : !1;
  }
  formatAlignment(t) {
    if (!this.editor) return !1;
    const e = document.querySelector(".visual-editor .erased-image-node--selected, .visual-editor .erased-video-node--selected, .visual-editor .is-selected, .visual-editor img.is-selected, .visual-editor table.is-selected, .visual-editor .post-video.is-selected, .visual-editor .video-embed.is-selected");
    if (e) {
      const r = e.dataset.nodeKey || "";
      r && this.editor.update(() => {
        const o = Z(r);
        (yc(o) || Cc(o)) && o.setAlignment(t);
      }), e.dataset.align = t;
      const i = e.tagName === "IMG" ? e : e.querySelector("img");
      if (i) {
        const o = i.closest("figure");
        o && (o.classList.remove("media-left", "media-center", "media-right"), o.classList.add(`media-${t}`));
      }
      return !0;
    }
    return this.editor.dispatchCommand(du, t);
  }
  formatBlock(t) {
    if (!this.editor)
      return !1;
    const e = String(t ?? "").replace(/[<>]/g, "").toLowerCase();
    return this.editor.update(() => {
      const r = M();
      if (w(r)) {
        if (e === "blockquote") {
          Eo(r, () => Ur());
          return;
        }
        if (["h1", "h2", "h3", "h4"].includes(e)) {
          Eo(r, () => Ze(e));
          return;
        }
        Eo(r, () => U());
      }
    }), this.editor.focus(), !0;
  }
}
function hr(n = null) {
  const t = document.querySelector("#body-editor"), e = document.querySelector("#visual-editor");
  if (!t || !e)
    return "";
  const r = typeof n == "string" ? n : window.erasedEditor?.getHTML?.() ?? e.innerHTML;
  return t.value = r, r;
}
function bc() {
  const n = document.querySelector("#visual-editor"), t = document.querySelector("#body-editor"), e = document.querySelector("#content-form");
  if (!n || window.erasedEditor)
    return;
  const r = t?.value || n.innerHTML || "";
  window.erasedEditor = new F_({
    element: n,
    content: r,
    onUpdate(i) {
      hr(i);
    }
  }).mount(), e?.addEventListener("submit", () => {
    hr();
  }, !0), e?.addEventListener("formdata", (i) => {
    i.formData.set("body", hr());
  }), window.syncLexicalBody = hr, hr();
}
document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", bc) : bc();
