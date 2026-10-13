import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

export default class extends Controller {
    static values = {
        status: String
    }

    connect() {
        this.sortable = new Sortable(this.element, {
            group: 'shared',
            animation: 150,
            draggable: '.take-item',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onStart: () => {
                window.isDragging = true;
            },
            onEnd: (event) => {
                this.onDragEnd(event);
            }
        });
    }

    updateEmptyMessage(container) {
        const emptyMessage = container.querySelector('.empty-message');
        const hasTakes = container.querySelectorAll('.take-item').length > 0;

        if (emptyMessage) {
            if (hasTakes) {
                emptyMessage.classList.add('hidden');
            } else {
                emptyMessage.classList.remove('hidden');
            }
        }
    }

    async onDragEnd(event) {
        const takeId = event.item.dataset.takeId;
        const newStatus = event.to.dataset.sortableStatusValue;
        const oldStatus = event.from.dataset.sortableStatusValue;

        // Update empty messages for both containers
        this.updateEmptyMessage(event.from);
        this.updateEmptyMessage(event.to);

        if (oldStatus === newStatus) {
            setTimeout(() => { window.isDragging = false; }, 100);
            return;
        }

        try {
            const response = await fetch(`/take/${takeId}/update-status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: newStatus })
            });

            if (!response.ok) throw new Error('Update failed');

            await response.json();

            const card = event.item.querySelector('.take-card');
            if (card) {
                card.classList.remove('take-pending', 'take-todo', 'take-ongoing', 'take-finished');
                const statusMap = {
                    'PENDING': 'take-pending',
                    'TO DO': 'take-todo',
                    'ON GOING': 'take-ongoing',
                    'FINISHED': 'take-finished'
                };
                if (statusMap[newStatus]) {
                    card.classList.add(statusMap[newStatus]);
                }
            }

        } catch (error) {
            event.to.removeChild(event.item);
            event.from.insertBefore(event.item, event.from.children[event.oldIndex] || null);
            // Restore empty messages after rollback
            this.updateEmptyMessage(event.from);
            this.updateEmptyMessage(event.to);
            alert('Failed to update status');
        } finally {
            setTimeout(() => { window.isDragging = false; }, 100);
        }
    }

    disconnect() {
        if (this.sortable) {
            this.sortable.destroy();
        }
    }
}

