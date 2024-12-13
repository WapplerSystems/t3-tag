
class TagElement extends HTMLElement {
  constructor() {
    super(...arguments), this.element = null
  }

  connectedCallback() {
    console.debug('TagElement connectedCallback');

    return;
    this.element = document.getElementById(this.getAttribute("recordFieldId") || ""), this.element && (this.registerEventHandler(), import("../../date-time-picker").then((({default: e}) => {
      e.initialize(this.element)
    })))
  }

  registerEventHandler() {

  }
}

window.customElements.define("typo3-formengine-element-tag", TagElement);
