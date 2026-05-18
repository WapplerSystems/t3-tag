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

import { AjaxResponse } from '@typo3/core/ajax/ajax-response';
import AjaxRequest from '@typo3/core/ajax/ajax-request';
import DocumentService from '@typo3/core/document-service';
import RegularEvent from '@typo3/core/event/regular-event';
import FormEngine from '@typo3/backend/form-engine';
import FormEngineSuggest from '@typo3/backend/form-engine-suggest';

interface SuggestResultItem {
  uid: number;
  table: string;
  label: string;
  title?: string;
}

interface CreateTagResponse {
  uid?: number;
  title?: string;
  error?: string;
}

/**
 * Module: @wapplersystems/tag/backend/form-engine/element/tag-element
 *
 * Manages the chip-based tag input field in TYPO3's form engine.
 *
 * Architecture:
 * - A hidden <select> with data-formengine-input-name is kept for compatibility
 *   with TYPO3's FormEngine (suggest, validation, hidden-field sync).
 * - A MutationObserver watches that select for changes and keeps the visible
 *   chips display in sync.
 * - Chip removal updates the select and calls FormEngine.updateHiddenFieldValueFromSelect.
 * - Enter on the suggest input creates a new sys_tag record via AJAX if no
 *   exact match is found in the current suggestion dropdown.
 */
class TagElement {
  private readonly fieldId: string;
  private readonly elementName: string;
  private readonly createPid: number;
  private readonly signature: string;

  private wrapEl: HTMLElement;
  private chipsEl: HTMLElement;
  private selectEl: HTMLSelectElement;
  private hiddenInput: HTMLInputElement;
  private suggestInput: HTMLInputElement;

  constructor(fieldId: string, elementName: string, createPid: number, signature: string) {
    this.fieldId = fieldId;
    this.elementName = elementName;
    this.createPid = createPid;
    this.signature = signature;

    DocumentService.ready().then((): void => {
      this.wrapEl = document.getElementById(this.fieldId + '-wrap') as HTMLElement;
      this.chipsEl = document.getElementById(this.fieldId + '-chips') as HTMLElement;
      this.selectEl = document.getElementById(this.fieldId) as HTMLSelectElement;
      this.suggestInput = this.wrapEl.querySelector('.t3-form-suggest') as HTMLInputElement;

      // The hidden submission input is found via the form's named elements to
      // avoid issues with special characters ([ ]) in CSS attribute selectors.
      const form = document.querySelector<HTMLFormElement>('form[name="editform"]');
      this.hiddenInput = form?.elements.namedItem(this.elementName) as HTMLInputElement;

      // Let TYPO3's built-in suggest handle autocomplete for existing tags
      new FormEngineSuggest(this.suggestInput);

      this.observeSelect();
      this.registerChipRemoval();
      this.registerSuggestEnter();
      this.registerSuggestItemChosen();
    });
  }

  /**
   * Watch the hidden select for option changes made by TYPO3's suggest system.
   * Any change is mirrored to the visible chip display.
   */
  private observeSelect(): void {
    new MutationObserver((): void => {
      this.syncChipsFromSelect();
    }).observe(this.selectEl, { childList: true });
  }

  /**
   * Rebuild the chip display to match the current state of the hidden select.
   * Only adds/removes the chips that actually changed.
   */
  private syncChipsFromSelect(): void {
    const existingChipValues = new Set<string>(
      Array.from(this.chipsEl.querySelectorAll<HTMLElement>('.tag-chip'))
        .map((el) => el.dataset.value ?? '')
    );
    const selectOptionValues = new Set<string>(
      Array.from(this.selectEl.options).map((opt) => opt.value)
    );

    // Remove chips whose option was removed from the select
    existingChipValues.forEach((value: string): void => {
      if (!selectOptionValues.has(value)) {
        this.chipsEl.querySelector(`.tag-chip[data-value="${CSS.escape(value)}"]`)?.remove();
      }
    });

    // Add chips for newly added select options
    Array.from(this.selectEl.options).forEach((opt: HTMLOptionElement): void => {
      if (!existingChipValues.has(opt.value)) {
        this.addChip(opt.value, opt.textContent?.trim() ?? opt.value);
      }
    });
  }

  /**
   * Create and insert a chip element into the chip container.
   */
  private addChip(value: string, title: string): void {
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
   * Delegate click handler for chip removal buttons.
   */
  private registerChipRemoval(): void {
    new RegularEvent('click', (e: Event): void => {
      const removeBtn = (e.target as HTMLElement).closest<HTMLElement>('.tag-chip-remove');
      if (!removeBtn) {
        return;
      }
      const value = removeBtn.dataset.value ?? '';

      // Remove chip from display
      this.chipsEl.querySelector(`.tag-chip[data-value="${CSS.escape(value)}"]`)?.remove();

      // Remove from hidden select and sync hidden submission field
      this.selectEl.querySelector(`option[value="${CSS.escape(value)}"]`)?.remove();
      FormEngine.updateHiddenFieldValueFromSelect(this.selectEl, this.hiddenInput);
      FormEngine.markFieldAsChanged(this.hiddenInput);
    }).bindTo(this.chipsEl);
  }

  /**
   * After a suggest item is chosen, clear the input field.
   */
  private registerSuggestItemChosen(): void {
    new RegularEvent('typo3:formengine:suggest-item-chosen', (): void => {
      this.suggestInput.value = '';
    }).bindTo(this.wrapEl);
  }

  /**
   * Handle Enter key on the suggest input.
   *
   * - If the dropdown contains an exact match (case-insensitive): select it.
   * - Otherwise: create a new sys_tag record in the configured storage page
   *   (createPid) and add it as a chip.
   */
  private registerSuggestEnter(): void {
    new RegularEvent('keydown', (e: KeyboardEvent): void => {
      if (e.key !== 'Enter') {
        return;
      }

      const title = this.suggestInput.value.trim();
      if (!title) {
        return;
      }

      e.preventDefault();

      const resultContainer = this.wrapEl.querySelector('typo3-backend-formengine-suggest-result-container');
      const isDropdownOpen = resultContainer !== null && !resultContainer.hasAttribute('hidden');

      if (isDropdownOpen) {
        // Try to find an exact match in the current suggestions
        let results: SuggestResultItem[] = [];
        try {
          results = JSON.parse(resultContainer.getAttribute('results') ?? '[]');
        } catch {
          results = [];
        }
        const exactMatch = results.find(
          (r: SuggestResultItem) => r.label.toLowerCase() === title.toLowerCase()
        );

        if (exactMatch) {
          const value = `sys_tag_${exactMatch.uid}`;
          FormEngine.setSelectOptionFromExternalSource(
            this.elementName,
            value,
            exactMatch.label,
            exactMatch.label
          );
          this.suggestInput.value = '';
          (resultContainer as HTMLElement).hidden = true;
          return;
        }

        // No exact match – close the dropdown and fall through to create
        (resultContainer as HTMLElement).hidden = true;
      }

      if (this.createPid > 0) {
        this.createTag(title);
      }
    }).bindTo(this.suggestInput);
  }

  /**
   * POST to the tag_create AJAX endpoint, add the returned tag as a chip.
   */
  private createTag(title: string): void {
    const tableName = this.suggestInput.dataset.tablename ?? '';
    const fieldName = this.suggestInput.dataset.fieldname ?? '';

    new AjaxRequest(TYPO3.settings.ajaxUrls.tag_create)
      .post({
        title,
        pid: this.createPid,
        tableName,
        fieldName,
        signature: this.signature,
      })
      .then(async (response: AjaxResponse): Promise<void> => {
        const data: CreateTagResponse = await response.resolve();
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