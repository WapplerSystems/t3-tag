/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import DocumentService from '@typo3/core/document-service.js';
import RegularEvent from '@typo3/core/event/regular-event.js';
import FormEngine from '@typo3/backend/form-engine.js';
import FormEngineSuggest from '@typo3/backend/form-engine-suggest.js';

/**
 * Chip-based tag input for TYPO3's FormEngine.
 *
 * A hidden <select> (data-formengine-input-name) serves as the source of truth
 * for FormEngine. A MutationObserver keeps the visible chip display in sync with
 * that select. TYPO3's built-in suggest handles autocomplete; Enter creates a
 * new sys_tag record via AJAX when no exact match exists.
 */
class TagElement {
  constructor(fieldId, elementName, createPid, signature) {
    this.fieldId = fieldId;
    this.elementName = elementName;
    this.createPid = createPid;
    this.signature = signature;

    DocumentService.ready().then(() => {
      this.wrapEl = document.getElementById(this.fieldId + '-wrap');
      this.chipsEl = document.getElementById(this.fieldId + '-chips');
      this.selectEl = document.getElementById(this.fieldId);
      this.suggestInput = this.wrapEl.querySelector('.t3-form-suggest');

      // Locate the submission hidden input via the form's named-element API
      // to avoid CSS selector escaping issues with names like data[table][uid][field].
      const form = document.querySelector('form[name="editform"]');
      this.hiddenInput = form?.elements.namedItem(this.elementName);

      new FormEngineSuggest(this.suggestInput);

      this.observeSelect();
      this.registerChipRemoval();
      this.registerSuggestEnter();
      this.registerSuggestItemChosen();
    });
  }

  /**
   * Watch the hidden select for children added/removed by FormEngine's suggest.
   */
  observeSelect() {
    new MutationObserver(() => {
      this.syncChipsFromSelect();
    }).observe(this.selectEl, { childList: true });
  }

  /**
   * Mirror the hidden select's current option set to the visible chip display.
   */
  syncChipsFromSelect() {
    const existingValues = new Set(
      Array.from(this.chipsEl.querySelectorAll('.tag-chip'))
        .map(el => el.dataset.value ?? '')
    );
    const selectValues = new Set(
      Array.from(this.selectEl.options).map(opt => opt.value)
    );

    // Remove chips no longer in the select
    existingValues.forEach(value => {
      if (!selectValues.has(value)) {
        this.chipsEl.querySelector(`.tag-chip[data-value="${CSS.escape(value)}"]`)?.remove();
      }
    });

    // Add chips for new select options
    Array.from(this.selectEl.options).forEach(opt => {
      if (!existingValues.has(opt.value)) {
        this.addChip(opt.value, opt.textContent?.trim() ?? opt.value);
      }
    });
  }

  /**
   * Insert a new chip into the chip container.
   */
  addChip(value, title) {
    const chip = document.createElement('span');
    chip.className = 'tag-chip';
    chip.dataset.value = value;

    const titleSpan = document.createElement('span');
    titleSpan.className = 'tag-chip-title';
    titleSpan.textContent = title;

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'tag-chip-remove';
    removeBtn.setAttribute('aria-label', 'Remove tag');
    removeBtn.dataset.value = value;
    removeBtn.innerHTML = '&#x2715;';

    chip.append(titleSpan, removeBtn);
    this.chipsEl.append(chip);
  }

  /**
   * Delegate click on the chips container to handle the × buttons.
   */
  registerChipRemoval() {
    new RegularEvent('click', (e) => {
      const removeBtn = e.target.closest('.tag-chip-remove');
      if (!removeBtn) return;

      const value = removeBtn.dataset.value ?? '';

      this.chipsEl.querySelector(`.tag-chip[data-value="${CSS.escape(value)}"]`)?.remove();
      this.selectEl.querySelector(`option[value="${CSS.escape(value)}"]`)?.remove();

      FormEngine.updateHiddenFieldValueFromSelect(this.selectEl, this.hiddenInput);
      FormEngine.markFieldAsChanged(this.hiddenInput);
    }).bindTo(this.chipsEl);
  }

  /**
   * Clear the suggest input text after a dropdown item was chosen.
   */
  registerSuggestItemChosen() {
    new RegularEvent('typo3:formengine:suggest-item-chosen', () => {
      this.suggestInput.value = '';
    }).bindTo(this.wrapEl);
  }

  /**
   * Handle Enter on the suggest input:
   * - exact case-insensitive match in open dropdown → select it
   * - no match → create new sys_tag via AJAX (requires createPid > 0)
   */
  registerSuggestEnter() {
    new RegularEvent('keydown', (e) => {
      if (e.key !== 'Enter') return;

      const title = this.suggestInput.value.trim();
      if (!title) return;

      e.preventDefault();

      const resultContainer = this.wrapEl.querySelector(
        'typo3-backend-formengine-suggest-result-container'
      );
      const isDropdownOpen = resultContainer !== null && !resultContainer.hasAttribute('hidden');

      if (isDropdownOpen) {
        let results = [];
        try {
          results = JSON.parse(resultContainer.getAttribute('results') ?? '[]');
        } catch {
          results = [];
        }

        const exactMatch = results.find(
          r => r.label.toLowerCase() === title.toLowerCase()
        );

        if (exactMatch) {
          FormEngine.setSelectOptionFromExternalSource(
            this.elementName,
            `sys_tag_${exactMatch.uid}`,
            exactMatch.label,
            exactMatch.label
          );
          this.suggestInput.value = '';
          resultContainer.hidden = true;
          return;
        }

        resultContainer.hidden = true;
      }

      if (this.createPid > 0) {
        this.createTag(title);
      }
    }).bindTo(this.suggestInput);
  }

  /**
   * POST to tag_create, then add the returned tag as an option + chip.
   */
  createTag(title) {
    const tableName = this.suggestInput.dataset.tablename ?? '';
    const fieldName = this.suggestInput.dataset.fieldname ?? '';

    new AjaxRequest(TYPO3.settings.ajaxUrls.tag_create)
      .post({ title, pid: this.createPid, tableName, fieldName, signature: this.signature })
      .then(async response => {
        const data = await response.resolve();
        if (data.uid) {
          const value = `sys_tag_${data.uid}`;
          const option = document.createElement('option');
          option.value = value;
          option.textContent = data.title ?? title;
          this.selectEl.append(option);

          FormEngine.updateHiddenFieldValueFromSelect(this.selectEl, this.hiddenInput);
          FormEngine.markFieldAsChanged(this.hiddenInput);
          this.suggestInput.value = '';
        }
      });
  }
}

export default TagElement;