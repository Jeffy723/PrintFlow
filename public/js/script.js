document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form[data-confirm]');

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.dataset.confirm || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const actions = document.querySelectorAll('a[data-confirm]');
    actions.forEach((link) => {
        link.addEventListener('click', (event) => {
            const message = link.dataset.confirm || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const itemList = document.getElementById('order-items');
    const addItemButton = document.querySelector('[data-add-item]');

    if (itemList && addItemButton) {
        const buildItemRow = (index) => {
            const row = document.createElement('div');
            row.className = 'order-item-row';

            row.innerHTML = `
                <div>
                    <label>Service</label>
                    <select name="items[${index}][service_id]" required>
                        <option value="">Select service</option>
                        ${Array.from(document.querySelectorAll('#order-items select[name^="items[0][service_id]"] option')).map((option) => {
                            const cloned = option.cloneNode(true);
                            return cloned.outerHTML;
                        }).join('')}
                    </select>
                </div>
                <div>
                    <label>Quantity</label>
                    <input type="number" name="items[${index}][quantity]" min="1" value="1" required>
                </div>
                <div>
                    <label>Specifications</label>
                    <textarea name="items[${index}][specifications]" rows="2" placeholder="Optional details"></textarea>
                </div>
                <div class="item-actions">
                    <button type="button" class="btn btn-secondary btn-small" data-remove-item>Remove</button>
                </div>
            `;

            return row;
        };

        addItemButton.addEventListener('click', () => {
            const currentRows = itemList.querySelectorAll('.order-item-row');
            const nextIndex = currentRows.length;
            itemList.appendChild(buildItemRow(nextIndex));
        });

        itemList.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-remove-item]');
            if (!trigger) {
                return;
            }

            const row = trigger.closest('.order-item-row');
            const rows = itemList.querySelectorAll('.order-item-row');

            if (rows.length > 1 && row) {
                row.remove();

                const remainingRows = itemList.querySelectorAll('.order-item-row');
                remainingRows.forEach((remainingRow, index) => {
                    const serviceSelect = remainingRow.querySelector('select');
                    const quantityInput = remainingRow.querySelector('input[type="number"]');
                    const specsInput = remainingRow.querySelector('textarea');

                    if (serviceSelect) {
                        serviceSelect.name = `items[${index}][service_id]`;
                    }

                    if (quantityInput) {
                        quantityInput.name = `items[${index}][quantity]`;
                    }

                    if (specsInput) {
                        specsInput.name = `items[${index}][specifications]`;
                    }
                });
            }
        });
    }
});
