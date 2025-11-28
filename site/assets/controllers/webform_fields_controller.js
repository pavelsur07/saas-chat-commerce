import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['textarea', 'container', 'list'];

    connect() {
        this.initFromTextarea();
        this.render();
    }

    initFromTextarea() {
        this.fields = [];

        if (!this.hasTextareaTarget) {
            return;
        }

        const raw = this.textareaTarget.value || '';
        if (!raw.trim()) {
            this.fields = [
                {
                    key: 'name',
                    label: 'Ваше имя',
                    type: 'text',
                    required: true,
                    placeholder: '',
                    options: [],
                },
                {
                    key: 'phone',
                    label: 'Телефон',
                    type: 'tel',
                    required: true,
                    placeholder: '',
                    options: [],
                },
                {
                    key: 'comment',
                    label: 'Комментарий',
                    type: 'textarea',
                    required: false,
                    placeholder: '',
                    options: [],
                },
            ];
            this.syncToTextarea();
            return;
        }

        try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                this.fields = parsed.map((f, index) => {
                    return {
                        key: typeof f.key === 'string' && f.key.trim() ? f.key : `field_${index + 1}`,
                        label: typeof f.label === 'string' ? f.label : `Поле ${index + 1}`,
                        type: typeof f.type === 'string' ? f.type : 'text',
                        required: Boolean(f.required),
                        placeholder: typeof f.placeholder === 'string' ? f.placeholder : '',
                        options: Array.isArray(f.options) ? f.options : [],
                    };
                });
            }
        } catch (e) {
            this.fields = [];
        }

        if (!this.fields.length) {
            this.fields = [
                {
                    key: 'name',
                    label: 'Ваше имя',
                    type: 'text',
                    required: true,
                    placeholder: '',
                    options: [],
                },
            ];
            this.syncToTextarea();
        }
    }

    syncToTextarea() {
        if (!this.hasTextareaTarget) {
            return;
        }
        this.textareaTarget.value = JSON.stringify(this.fields, null, 2);
    }

    addField() {
        const index = this.fields.length + 1;
        this.fields.push({
            key: `field_${index}`,
            label: `Поле ${index}`,
            type: 'text',
            required: false,
            placeholder: '',
            options: [],
        });
        this.syncToTextarea();
        this.render();
    }

    removeField(event) {
        const index = parseInt(event.currentTarget.dataset.index, 10);
        if (Number.isNaN(index)) {
            return;
        }
        this.fields.splice(index, 1);
        this.syncToTextarea();
        this.render();
    }

    moveUp(event) {
        const index = parseInt(event.currentTarget.dataset.index, 10);
        if (Number.isNaN(index) || index <= 0) {
            return;
        }
        const tmp = this.fields[index];
        this.fields[index] = this.fields[index - 1];
        this.fields[index - 1] = tmp;
        this.syncToTextarea();
        this.render();
    }

    moveDown(event) {
        const index = parseInt(event.currentTarget.dataset.index, 10);
        if (Number.isNaN(index) || index >= this.fields.length - 1) {
            return;
        }
        const tmp = this.fields[index];
        this.fields[index] = this.fields[index + 1];
        this.fields[index + 1] = tmp;
        this.syncToTextarea();
        this.render();
    }

    handleChange(event) {
        const index = parseInt(event.currentTarget.dataset.index, 10);
        const fieldName = event.currentTarget.dataset.field;
        if (Number.isNaN(index) || !fieldName) {
            return;
        }

        const value = event.currentTarget.type === 'checkbox'
            ? event.currentTarget.checked
            : event.currentTarget.value;

        if (!this.fields[index]) {
            return;
        }

        this.fields[index][fieldName] = value;
        this.syncToTextarea();
    }

    render() {
        if (!this.hasListTarget) {
            return;
        }

        this.listTarget.innerHTML = '';

        const allowedTypes = ['text', 'textarea', 'email', 'tel', 'checkbox', 'select'];

        this.fields.forEach((field, index) => {
            const row = document.createElement('div');
            row.className = 'flex flex-col gap-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2';

            const header = document.createElement('div');
            header.className = 'flex items-center justify-between gap-2';

            const labelInput = document.createElement('input');
            labelInput.type = 'text';
            labelInput.value = field.label || '';
            labelInput.placeholder = 'Метка поля';
            labelInput.className = 'flex-1 rounded border border-gray-300 px-2 py-1 text-xs';
            labelInput.dataset.index = String(index);
            labelInput.dataset.field = 'label';
            labelInput.addEventListener('input', this.handleChange.bind(this));

            const keySpan = document.createElement('span');
            keySpan.className = 'ml-2 text-[10px] text-gray-500';
            keySpan.textContent = field.key;

            const controls = document.createElement('div');
            controls.className = 'flex items-center gap-1';

            const upBtn = document.createElement('button');
            upBtn.type = 'button';
            upBtn.textContent = '↑';
            upBtn.className = 'rounded border border-gray-300 px-1 text-[10px]';
            upBtn.dataset.index = String(index);
            upBtn.addEventListener('click', this.moveUp.bind(this));

            const downBtn = document.createElement('button');
            downBtn.type = 'button';
            downBtn.textContent = '↓';
            downBtn.className = 'rounded border border-gray-300 px-1 text-[10px]';
            downBtn.dataset.index = String(index);
            downBtn.addEventListener('click', this.moveDown.bind(this));

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.textContent = '✕';
            removeBtn.className = 'rounded border border-red-200 px-2 text-[10px] text-red-600';
            removeBtn.dataset.index = String(index);
            removeBtn.addEventListener('click', this.removeField.bind(this));

            controls.appendChild(upBtn);
            controls.appendChild(downBtn);
            controls.appendChild(removeBtn);

            header.appendChild(labelInput);
            header.appendChild(keySpan);
            header.appendChild(controls);

            const body = document.createElement('div');
            body.className = 'flex flex-wrap items-center gap-2 pt-1';

            const typeSelect = document.createElement('select');
            typeSelect.className = 'rounded border border-gray-300 px-2 py-1 text-xs';
            typeSelect.dataset.index = String(index);
            typeSelect.dataset.field = 'type';
            allowedTypes.forEach((t) => {
                const opt = document.createElement('option');
                opt.value = t;
                opt.textContent = t;
                if (t === field.type) {
                    opt.selected = true;
                }
                typeSelect.appendChild(opt);
            });
            typeSelect.addEventListener('change', this.handleChange.bind(this));

            const requiredLabel = document.createElement('label');
            requiredLabel.className = 'flex items-center gap-1 text-[11px] text-gray-700';

            const requiredInput = document.createElement('input');
            requiredInput.type = 'checkbox';
            requiredInput.checked = Boolean(field.required);
            requiredInput.dataset.index = String(index);
            requiredInput.dataset.field = 'required';
            requiredInput.addEventListener('change', this.handleChange.bind(this));

            const requiredText = document.createElement('span');
            requiredText.textContent = 'Обязательное';

            requiredLabel.appendChild(requiredInput);
            requiredLabel.appendChild(requiredText);

            const placeholderInput = document.createElement('input');
            placeholderInput.type = 'text';
            placeholderInput.value = field.placeholder || '';
            placeholderInput.placeholder = 'Placeholder';
            placeholderInput.className = 'flex-1 rounded border border-gray-300 px-2 py-1 text-xs';
            placeholderInput.dataset.index = String(index);
            placeholderInput.dataset.field = 'placeholder';
            placeholderInput.addEventListener('input', this.handleChange.bind(this));

            body.appendChild(typeSelect);
            body.appendChild(requiredLabel);
            body.appendChild(placeholderInput);

            row.appendChild(header);
            row.appendChild(body);

            this.listTarget.appendChild(row);
        });
    }
}
