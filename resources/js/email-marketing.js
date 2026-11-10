/**
 * Email Marketing (admin only). Uses the CRM's own helpers, passed in from app.js,
 * so tables, toasts, errors and navigation behave exactly like the rest of the app.
 */
export function initEmailMarketing(ctx) {
    const { api, showToast, escapeHtml, formatDate, getErrorMessage, setFormStatus, setActiveView, resetTable, buildQuery, debounce } = ctx;

    const $ = (id) => document.getElementById(id);
    const dom = {
        listsTable: $('email-lists-table'),
        listsSearch: $('email-lists-search'),
        listsLoadMore: $('email-lists-load-more'),
        listNew: $('email-list-new'),
        listEditor: $('email-list-editor'),
        listForm: $('email-list-form'),
        listFormTitle: $('email-list-form-title'),
        listFormStatus: $('email-list-form-status'),
        listCancel: $('email-list-cancel'),
        detailTitle: $('email-list-detail-title'),
        detailDescription: $('email-list-detail-description'),
        detailEdit: $('email-list-detail-edit'),
        detailBack: $('email-list-detail-back'),
        subscribedCount: $('email-list-subscribed-count'),
        unsubscribedCount: $('email-list-unsubscribed-count'),
        bouncedCount: $('email-list-bounced-count'),
        subscribersTable: $('email-subscribers-table'),
        subscribersSearch: $('email-subscribers-search'),
        subscribersStatus: $('email-subscribers-status'),
        subscribersLoadMore: $('email-subscribers-load-more'),
        subscriberNew: $('email-subscriber-new'),
        subscriberImport: $('email-subscriber-import'),
        subscriberModal: $('email-subscriber-modal'),
        subscriberModalTitle: $('email-subscriber-modal-title'),
        subscriberModalSubtitle: $('email-subscriber-modal-subtitle'),
        subscriberForm: $('email-subscriber-form'),
        subscriberFormStatus: $('email-subscriber-form-status'),
        subscriberSubmit: $('email-subscriber-submit'),
        subscriberConsentRecord: $('email-subscriber-consent-record'),
        statusModal: $('email-status-modal'),
        statusModalTitle: $('email-status-modal-title'),
        statusModalSubtitle: $('email-status-modal-subtitle'),
        statusForm: $('email-status-form'),
        statusReasonField: $('email-status-reason-field'),
        statusNoteLabel: $('email-status-note-label'),
        statusFormStatus: $('email-status-form-status'),
        statusSubmit: $('email-status-submit'),
        importModal: $('email-import-modal'),
        importSubtitle: $('email-import-modal-subtitle'),
        importUpload: $('email-import-upload'),
        importUploadStatus: $('email-import-upload-status'),
        importMapping: $('email-import-mapping'),
        importMappingStatus: $('email-import-mapping-status'),
        importSample: $('email-import-sample'),
        importSubmit: $('email-import-submit'),
        importBack: $('email-import-back'),
        importSummary: $('email-import-summary'),
        importTotals: $('email-import-totals'),
        importDetails: $('email-import-details'),
    };

    if (!dom.listsTable) {
        return { onView() {}, openList() {}, openCampaign() {}, currentListId: () => null, currentCampaignId: () => null };
    }

    const state = {
        lists: [],
        listsPage: { page: 1, lastPage: 1 },
        listsSearch: '',
        editingListId: null,
        currentList: null,
        subscribers: [],
        subscribersPage: { page: 1, lastPage: 1 },
        subscribersSearch: '',
        subscribersStatus: 'all',
        editingSubscriber: null,
        statusTarget: null,
        importPreview: null,
        consentSources: null,
    };

    const statusLabels = { subscribed: 'Subscribed', unsubscribed: 'Unsubscribed', bounced: 'Bounced' };
    const statusPillClass = { subscribed: 'pill success', unsubscribed: 'pill outline', bounced: 'pill email-pill-bounced' };
    const outcomeLabels = { added: 'Added', duplicate: 'Duplicate', skipped: 'Skipped', invalid: 'Invalid' };

    function emptyRow(table, className, message, columns) {
        const row = document.createElement('div');
        row.className = `table-row table-empty ${className}`;
        row.innerHTML = `<span>${escapeHtml(message)}</span>${'<span></span>'.repeat(columns - 1)}`;
        table.appendChild(row);
    }

    function setLoadMore(button, pagination) {
        if (!button) return;
        button.style.display = pagination.page < pagination.lastPage ? 'inline-flex' : 'none';
        button.disabled = false;
    }

    async function ensureConsentSources() {
        if (state.consentSources) return state.consentSources;
        const response = await api.get('/api/email-subscribers/options');
        state.consentSources = response?.data?.consent_sources || {};
        document.querySelectorAll('[data-email-consent-select]').forEach((select) => {
            select.innerHTML = '<option value="">Choose…</option>' + Object.entries(state.consentSources)
                .map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`)
                .join('');
        });
        return state.consentSources;
    }

    // ---- Modals ----

    function openModal(modal) {
        if (modal) modal.hidden = false;
    }

    function closeModal(modal) {
        if (modal) modal.hidden = true;
    }

    [dom.subscriberModal, dom.statusModal, dom.importModal, $('email-send-modal')].forEach((modal) => {
        if (!modal) return;
        modal.addEventListener('click', (event) => {
            if (event.target === modal || event.target.closest('[data-email-modal-close]')) closeModal(modal);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        [dom.subscriberModal, dom.statusModal, dom.importModal, $('email-send-modal')].forEach(closeModal);
    });

    // ---- Lists ----

    async function loadLists(append = false) {
        dom.listsLoadMore.disabled = true;
        if (!append) {
            state.listsPage = { page: 1, lastPage: 1 };
            resetTable(dom.listsTable);
            emptyRow(dom.listsTable, 'email-lists', 'Loading lists...', 5);
        }

        try {
            const page = append ? state.listsPage.page + 1 : 1;
            const response = await api.get(`/api/email-lists${buildQuery({ per_page: 20, page, search: state.listsSearch })}`);
            const items = response?.data?.data ?? [];
            state.lists = append ? [...state.lists, ...items] : items;
            state.listsPage = { page: response?.data?.current_page ?? page, lastPage: response?.data?.last_page ?? page };
            renderLists();
        } catch (error) {
            resetTable(dom.listsTable);
            emptyRow(dom.listsTable, 'email-lists', 'Unable to load lists.', 5);
        } finally {
            setLoadMore(dom.listsLoadMore, state.listsPage);
        }
    }

    function renderLists() {
        resetTable(dom.listsTable);

        if (!state.lists.length) {
            emptyRow(dom.listsTable, 'email-lists', state.listsSearch ? 'No lists match that search.' : 'No lists yet. Click New list to create one.', 5);
            return;
        }

        state.lists.forEach((list) => {
            const row = document.createElement('div');
            row.className = 'table-row email-lists';
            const others = list.subscribers_count - list.subscribed_count;
            row.innerHTML = `
                <span><button type="button" class="email-link" data-action="view" data-id="${list.id}">${escapeHtml(list.name)}</button></span>
                <span class="email-muted">${escapeHtml(list.description || '--')}</span>
                <span>${list.subscribed_count.toLocaleString('en-GB')}${others > 0 ? `<small class="email-subtext">${others.toLocaleString('en-GB')} unsubscribed or bounced</small>` : ''}</span>
                <span>${formatDate(list.created_at)}</span>
                <div class="row-actions">
                    <button class="btn btn-outline btn-small" data-action="view" data-id="${list.id}">View</button>
                    <button class="btn btn-outline btn-small" data-action="edit" data-id="${list.id}">Edit</button>
                    <button class="btn btn-outline btn-small" data-action="delete" data-id="${list.id}">Delete</button>
                </div>
            `;
            dom.listsTable.appendChild(row);
        });
    }

    function openListEditor(list = null) {
        state.editingListId = list?.id ?? null;
        dom.listFormTitle.textContent = list ? `Edit ${list.name}` : 'New list';
        dom.listForm.elements.name.value = list?.name || '';
        dom.listForm.elements.description.value = list?.description || '';
        setFormStatus(dom.listFormStatus, '');
        dom.listEditor.hidden = false;
        dom.listEditor.scrollIntoView({ behavior: 'smooth', block: 'start' });
        dom.listForm.elements.name.focus({ preventScroll: true });
    }

    function closeListEditor() {
        state.editingListId = null;
        dom.listEditor.hidden = true;
    }

    dom.listNew.addEventListener('click', () => openListEditor());
    dom.listCancel.addEventListener('click', closeListEditor);
    dom.listsLoadMore.addEventListener('click', () => loadLists(true));
    dom.listsSearch.addEventListener('input', debounce(() => {
        state.listsSearch = dom.listsSearch.value.trim();
        loadLists();
    }));

    dom.listForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = {
            name: dom.listForm.elements.name.value.trim(),
            description: dom.listForm.elements.description.value.trim() || null,
        };
        if (!payload.name) {
            setFormStatus(dom.listFormStatus, 'Give the list a name.', true);
            return;
        }

        const submit = dom.listForm.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            if (state.editingListId) {
                const response = await api.put(`/api/email-lists/${state.editingListId}`, payload);
                if (state.currentList?.id === state.editingListId) state.currentList = response.data.data;
                showToast('List updated.');
            } else {
                await api.post('/api/email-lists', payload);
                showToast('List created.');
            }
            closeListEditor();
            await loadLists();
        } catch (error) {
            setFormStatus(dom.listFormStatus, getErrorMessage(error, 'Unable to save list.'), true);
        } finally {
            submit.disabled = false;
        }
    });

    dom.listsTable.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const list = state.lists.find((item) => Number(item.id) === Number(button.dataset.id));
        if (!list) return;

        if (button.dataset.action === 'view') openList(list.id);
        if (button.dataset.action === 'edit') openListEditor(list);
        if (button.dataset.action === 'delete') {
            if (!window.confirm(`Delete the list “${list.name}”? Its subscribers stay in the system, and anyone who unsubscribed stays unsubscribed.`)) return;
            try {
                await api.delete(`/api/email-lists/${list.id}`);
                if (state.editingListId === list.id) closeListEditor();
                showToast('List deleted.');
                await loadLists();
            } catch (error) {
                showToast(getErrorMessage(error, 'Unable to delete list.'), true);
            }
        }
    });

    // ---- List detail and subscribers ----

    function openList(id) {
        state.currentList = { id: Number(id) };
        state.subscribersSearch = '';
        state.subscribersStatus = 'all';
        dom.subscribersSearch.value = '';
        dom.subscribersStatus.value = 'all';
        setActiveView('email-list-detail');
    }

    async function loadListDetail() {
        if (!state.currentList?.id) return;
        try {
            const response = await api.get(`/api/email-lists/${state.currentList.id}`);
            state.currentList = response.data.data;
            renderListDetail();
        } catch (error) {
            showToast(getErrorMessage(error, 'Unable to load list.'), true);
            activeTab = 'lists';
            setActiveView('email-marketing');
        }
    }

    function renderListDetail() {
        const list = state.currentList;
        dom.detailTitle.textContent = list.name;
        dom.detailDescription.textContent = list.description || 'No description';
        dom.subscribedCount.textContent = Number(list.subscribed_count || 0).toLocaleString('en-GB');
        dom.unsubscribedCount.textContent = Number(list.unsubscribed_count || 0).toLocaleString('en-GB');
        dom.bouncedCount.textContent = Number(list.bounced_count || 0).toLocaleString('en-GB');
    }

    async function loadSubscribers(append = false) {
        if (!state.currentList?.id) return;
        dom.subscribersLoadMore.disabled = true;
        if (!append) {
            state.subscribersPage = { page: 1, lastPage: 1 };
            resetTable(dom.subscribersTable);
            emptyRow(dom.subscribersTable, 'email-subscribers', 'Loading subscribers...', 5);
        }

        try {
            const page = append ? state.subscribersPage.page + 1 : 1;
            const query = buildQuery({ per_page: 25, page, search: state.subscribersSearch, status: state.subscribersStatus });
            const response = await api.get(`/api/email-lists/${state.currentList.id}/subscribers${query}`);
            const items = response?.data?.data ?? [];
            state.subscribers = append ? [...state.subscribers, ...items] : items;
            state.subscribersPage = { page: response?.data?.current_page ?? page, lastPage: response?.data?.last_page ?? page };
            renderSubscribers();
        } catch (error) {
            resetTable(dom.subscribersTable);
            emptyRow(dom.subscribersTable, 'email-subscribers', 'Unable to load subscribers.', 5);
        } finally {
            setLoadMore(dom.subscribersLoadMore, state.subscribersPage);
        }
    }

    function renderSubscribers() {
        resetTable(dom.subscribersTable);

        if (!state.subscribers.length) {
            const filtered = state.subscribersSearch || state.subscribersStatus !== 'all';
            emptyRow(dom.subscribersTable, 'email-subscribers', filtered ? 'No subscribers match these filters.' : 'No subscribers yet. Add someone or import a CSV.', 5);
            return;
        }

        state.subscribers.forEach((subscriber) => {
            const name = [subscriber.first_name, subscriber.last_name].filter(Boolean).join(' ');
            const leftAt = subscriber.status === 'bounced' ? subscriber.bounced_at : subscriber.unsubscribed_at;
            const row = document.createElement('div');
            row.className = 'table-row email-subscribers';
            row.innerHTML = `
                <span><strong class="email-address">${escapeHtml(subscriber.email)}</strong>${name ? `<small class="email-subtext">${escapeHtml(name)}</small>` : ''}</span>
                <span><span class="${statusPillClass[subscriber.status] || 'pill outline'}">${statusLabels[subscriber.status] || escapeHtml(subscriber.status)}</span>${leftAt ? `<small class="email-subtext">${formatDate(leftAt)}</small>` : ''}</span>
                <span>${escapeHtml(subscriber.consent_source_label)}${subscriber.consent_note ? `<small class="email-subtext" title="${escapeHtml(subscriber.consent_note)}">${escapeHtml(subscriber.consent_note)}</small>` : ''}</span>
                <span>${formatDate(subscriber.added_to_list_at || subscriber.created_at)}</span>
                <div class="row-actions">
                    <button class="btn btn-outline btn-small" data-action="edit" data-id="${subscriber.id}">Edit</button>
                    ${subscriber.status === 'subscribed'
                        ? `<button class="btn btn-outline btn-small" data-action="unsubscribe" data-id="${subscriber.id}">Unsubscribe</button>`
                        : `<button class="btn btn-outline btn-small" data-action="resubscribe" data-id="${subscriber.id}">Resubscribe</button>`}
                    <button class="btn btn-outline btn-small" data-action="remove" data-id="${subscriber.id}">Remove</button>
                </div>
            `;
            dom.subscribersTable.appendChild(row);
        });
    }

    async function refreshDetail() {
        await Promise.all([loadListDetail(), loadSubscribers()]);
    }

    dom.detailBack.addEventListener('click', () => {
        activeTab = 'lists';
        setActiveView('email-marketing');
    });
    dom.detailEdit.addEventListener('click', () => {
        const list = state.currentList;
        activeTab = 'lists';
        setActiveView('email-marketing');
        if (list) openListEditor(list);
    });
    dom.subscribersLoadMore.addEventListener('click', () => loadSubscribers(true));
    dom.subscribersSearch.addEventListener('input', debounce(() => {
        state.subscribersSearch = dom.subscribersSearch.value.trim();
        loadSubscribers();
    }));
    dom.subscribersStatus.addEventListener('change', () => {
        state.subscribersStatus = dom.subscribersStatus.value;
        loadSubscribers();
    });

    dom.subscribersTable.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const subscriber = state.subscribers.find((item) => Number(item.id) === Number(button.dataset.id));
        if (!subscriber) return;

        if (button.dataset.action === 'edit') openSubscriberModal(subscriber);
        if (button.dataset.action === 'unsubscribe') openStatusModal(subscriber, 'unsubscribe');
        if (button.dataset.action === 'resubscribe') openStatusModal(subscriber, 'resubscribe');
        if (button.dataset.action === 'remove') {
            if (!window.confirm(`Remove ${subscriber.email} from “${state.currentList.name}”? ${subscriber.status === 'subscribed' ? 'They stay on any other lists.' : 'They stay unsubscribed everywhere.'}`)) return;
            try {
                await api.delete(`/api/email-lists/${state.currentList.id}/subscribers/${subscriber.id}`);
                showToast('Removed from list.');
                await refreshDetail();
            } catch (error) {
                showToast(getErrorMessage(error, 'Unable to remove subscriber.'), true);
            }
        }
    });

    // ---- Add / edit subscriber ----

    async function openSubscriberModal(subscriber = null) {
        await ensureConsentSources();
        state.editingSubscriber = subscriber;
        const form = dom.subscriberForm;
        form.reset();
        form.elements.email.value = subscriber?.email || '';
        form.elements.first_name.value = subscriber?.first_name || '';
        form.elements.last_name.value = subscriber?.last_name || '';
        form.elements.consent_note.value = subscriber?.consent_note || '';
        form.querySelectorAll('[data-email-new-only]').forEach((element) => {
            element.hidden = Boolean(subscriber);
        });
        dom.subscriberConsentRecord.hidden = !subscriber;
        dom.subscriberConsentRecord.textContent = subscriber
            ? `Consent: ${subscriber.consent_source_label}, recorded ${formatDate(subscriber.created_at)}.`
            : '';
        dom.subscriberModalTitle.textContent = subscriber ? 'Edit subscriber' : 'Add subscriber';
        dom.subscriberModalSubtitle.textContent = subscriber
            ? 'Changes apply on every list this person is on.'
            : 'Only add people who have agreed to receive marketing emails.';
        dom.subscriberSubmit.textContent = subscriber ? 'Save changes' : 'Add subscriber';
        setFormStatus(dom.subscriberFormStatus, '');
        openModal(dom.subscriberModal);
        form.elements.email.focus();
    }

    dom.subscriberNew.addEventListener('click', () => openSubscriberModal());

    dom.subscriberForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = dom.subscriberForm;
        const payload = {
            email: form.elements.email.value.trim(),
            first_name: form.elements.first_name.value.trim() || null,
            last_name: form.elements.last_name.value.trim() || null,
            consent_note: form.elements.consent_note.value.trim() || null,
        };
        if (!payload.email) {
            setFormStatus(dom.subscriberFormStatus, 'Enter an email address.', true);
            return;
        }
        if (!state.editingSubscriber) {
            payload.consent_source = form.elements.consent_source.value;
            if (!payload.consent_source) {
                setFormStatus(dom.subscriberFormStatus, 'Choose how this person gave consent.', true);
                return;
            }
        }

        dom.subscriberSubmit.disabled = true;
        try {
            if (state.editingSubscriber) {
                await api.put(`/api/email-subscribers/${state.editingSubscriber.id}`, payload);
                showToast('Subscriber updated.');
            } else {
                await api.post(`/api/email-lists/${state.currentList.id}/subscribers`, payload);
                showToast('Subscriber added.');
            }
            closeModal(dom.subscriberModal);
            await refreshDetail();
        } catch (error) {
            setFormStatus(dom.subscriberFormStatus, getErrorMessage(error, 'Unable to save subscriber.'), true);
        } finally {
            dom.subscriberSubmit.disabled = false;
        }
    });

    // ---- Unsubscribe / resubscribe ----

    function openStatusModal(subscriber, mode) {
        state.statusTarget = { subscriber, mode };
        const isResubscribe = mode === 'resubscribe';
        dom.statusForm.reset();
        dom.statusReasonField.hidden = isResubscribe;
        dom.statusModalTitle.textContent = isResubscribe ? 'Resubscribe' : 'Unsubscribe';
        dom.statusModalSubtitle.textContent = isResubscribe
            ? `${subscriber.email} will start receiving campaigns again, on every list they're on. Only do this if they've asked to rejoin.`
            : `${subscriber.email} will never be emailed again from any list, even if they're re-added or re-imported.`;
        dom.statusNoteLabel.textContent = isResubscribe ? 'Why are they being resubscribed? (required)' : 'Note (optional)';
        dom.statusForm.elements.note.placeholder = isResubscribe ? 'e.g. Emailed on 3 Nov asking to rejoin the newsletter' : 'e.g. Asked by phone';
        dom.statusSubmit.textContent = isResubscribe ? 'Resubscribe' : 'Unsubscribe';
        setFormStatus(dom.statusFormStatus, '');
        openModal(dom.statusModal);
        dom.statusForm.elements.note.focus();
    }

    dom.statusForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const target = state.statusTarget;
        if (!target) return;
        const note = dom.statusForm.elements.note.value.trim();
        if (target.mode === 'resubscribe' && !note) {
            setFormStatus(dom.statusFormStatus, 'Add a note saying why this person is being resubscribed.', true);
            return;
        }

        dom.statusSubmit.disabled = true;
        try {
            if (target.mode === 'resubscribe') {
                await api.post(`/api/email-subscribers/${target.subscriber.id}/resubscribe`, { note });
                showToast('Subscriber resubscribed.');
            } else {
                await api.post(`/api/email-subscribers/${target.subscriber.id}/unsubscribe`, { reason: dom.statusForm.elements.reason.value, note: note || null });
                showToast(dom.statusForm.elements.reason.value === 'bounced' ? 'Marked as bounced.' : 'Subscriber unsubscribed.');
            }
            closeModal(dom.statusModal);
            await refreshDetail();
        } catch (error) {
            setFormStatus(dom.statusFormStatus, getErrorMessage(error, 'Unable to update subscriber.'), true);
        } finally {
            dom.statusSubmit.disabled = false;
        }
    });

    // ---- CSV import ----

    function showImportStep(step) {
        dom.importUpload.hidden = step !== 'upload';
        dom.importMapping.hidden = step !== 'mapping';
        dom.importSummary.hidden = step !== 'summary';
        dom.importSubtitle.textContent = {
            upload: 'Upload a CSV file with one person per row.',
            mapping: 'Match the columns in your file, then confirm consent.',
            summary: 'Import complete.',
        }[step];
    }

    async function openImportModal() {
        await ensureConsentSources();
        state.importPreview = null;
        dom.importUpload.reset();
        dom.importMapping.reset();
        setFormStatus(dom.importUploadStatus, '');
        setFormStatus(dom.importMappingStatus, '');
        showImportStep('upload');
        openModal(dom.importModal);
    }

    function renderImportMapping(preview) {
        const options = preview.columns.map((column, index) => `<option value="${index}">${escapeHtml(column)}</option>`).join('');
        dom.importMapping.querySelectorAll('[data-email-column-select]').forEach((select) => {
            select.innerHTML = (select.hasAttribute('data-optional') ? '<option value="">Not in this file</option>' : '<option value="">Choose a column</option>') + options;
        });
        const { mapping } = preview;
        dom.importMapping.elements.map_email.value = mapping.email ?? '';
        dom.importMapping.elements.map_first_name.value = mapping.first_name ?? '';
        dom.importMapping.elements.map_last_name.value = mapping.last_name ?? '';
        dom.importMapping.elements.has_header.checked = true;

        const columns = preview.columns.length;
        const style = `style="grid-template-columns: repeat(${columns}, minmax(0, 1fr));"`;
        dom.importSample.innerHTML = `
            <div class="table-row table-header" ${style}>${preview.columns.map((column) => `<span>${escapeHtml(column)}</span>`).join('')}</div>
            ${preview.sample.map((row) => `<div class="table-row" ${style}>${preview.columns.map((_, index) => `<span>${escapeHtml(row[index] ?? '')}</span>`).join('')}</div>`).join('')}
        `;
        dom.importSubmit.textContent = `Import ${preview.rows.toLocaleString('en-GB')} ${preview.rows === 1 ? 'row' : 'rows'}`;
    }

    function renderImportSummary(summary) {
        const totals = [
            ['added', 'Added', 'New to this list'],
            ['duplicate', 'Duplicates', 'Already on the list or repeated'],
            ['skipped', 'Skipped', 'Previously unsubscribed or bounced'],
            ['invalid', 'Invalid', 'Missing or badly formatted email'],
        ];
        dom.importTotals.innerHTML = totals.map(([key, label, meta]) => `
            <div class="card">
                <div class="card-label">${label}</div>
                <div class="card-value">${Number(summary[key] || 0).toLocaleString('en-GB')}</div>
                <div class="card-meta">${meta}</div>
            </div>
        `).join('');

        const problems = (summary.details || []).filter((detail) => detail.outcome !== 'added');
        dom.importDetails.hidden = problems.length === 0;
        dom.importDetails.innerHTML = problems.length ? `
            <div class="table-row table-header email-import-details"><span>Row</span><span>Email</span><span>Result</span><span>Reason</span></div>
            ${problems.map((detail) => `
                <div class="table-row email-import-details">
                    <span>${detail.row ?? '--'}</span>
                    <span>${escapeHtml(detail.email || '(blank)')}</span>
                    <span>${outcomeLabels[detail.outcome] || escapeHtml(detail.outcome)}</span>
                    <span class="email-muted">${escapeHtml(detail.reason)}</span>
                </div>
            `).join('')}
        ` : '';
    }

    dom.subscriberImport.addEventListener('click', openImportModal);
    dom.importBack.addEventListener('click', () => {
        dom.importUpload.reset();
        showImportStep('upload');
    });

    dom.importUpload.addEventListener('submit', async (event) => {
        event.preventDefault();
        const file = dom.importUpload.elements.file.files[0];
        if (!file) {
            setFormStatus(dom.importUploadStatus, 'Choose a CSV file.', true);
            return;
        }

        const submit = dom.importUpload.querySelector('[type="submit"]');
        submit.disabled = true;
        setFormStatus(dom.importUploadStatus, 'Reading file…');
        try {
            const body = new FormData();
            body.append('file', file);
            const response = await api.post(`/api/email-lists/${state.currentList.id}/import/preview`, body);
            state.importPreview = response.data.data;
            renderImportMapping(state.importPreview);
            setFormStatus(dom.importUploadStatus, '');
            setFormStatus(dom.importMappingStatus, '');
            showImportStep('mapping');
        } catch (error) {
            setFormStatus(dom.importUploadStatus, getErrorMessage(error, 'Unable to read that file.'), true);
        } finally {
            submit.disabled = false;
        }
    });

    dom.importMapping.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = dom.importMapping.elements;
        const toIndex = (value) => (value === '' ? null : Number(value));
        if (form.map_email.value === '') {
            setFormStatus(dom.importMappingStatus, 'Choose which column holds the email address.', true);
            return;
        }
        if (!form.consent_source.value) {
            setFormStatus(dom.importMappingStatus, 'Choose how these people gave consent.', true);
            return;
        }
        if (!form.consent_confirmed.checked) {
            setFormStatus(dom.importMappingStatus, 'Confirm you have consent to email everyone in this file.', true);
            return;
        }

        dom.importSubmit.disabled = true;
        setFormStatus(dom.importMappingStatus, 'Importing…');
        try {
            const response = await api.post(`/api/email-lists/${state.currentList.id}/import`, {
                token: state.importPreview.token,
                has_header: form.has_header.checked,
                mapping: {
                    email: toIndex(form.map_email.value),
                    first_name: toIndex(form.map_first_name.value),
                    last_name: toIndex(form.map_last_name.value),
                },
                consent_source: form.consent_source.value,
                consent_note: form.consent_note.value.trim() || null,
                consent_confirmed: true,
            });
            renderImportSummary(response.data.data);
            showImportStep('summary');
            await refreshDetail();
        } catch (error) {
            setFormStatus(dom.importMappingStatus, getErrorMessage(error, 'Unable to import that file.'), true);
        } finally {
            dom.importSubmit.disabled = false;
        }
    });

    // ---- Campaigns / Lists tabs ----

    const tabInputs = document.querySelectorAll('input[name="email-marketing-tab"]');
    const panels = document.querySelectorAll('[data-email-panel]');
    let activeTab = 'campaigns';

    function showTab(tab) {
        activeTab = tab;
        tabInputs.forEach((input) => {
            input.checked = input.value === tab;
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.emailPanel !== tab;
        });
        if (tab === 'campaigns') loadCampaigns();
        if (tab === 'lists') loadLists();
    }

    tabInputs.forEach((input) => input.addEventListener('change', () => showTab(input.value)));

    // ---- Campaigns table ----

    const campaignDom = {
        table: $('email-campaigns-table'),
        search: $('email-campaigns-search'),
        status: $('email-campaigns-status'),
        loadMore: $('email-campaigns-load-more'),
        newButton: $('email-campaign-new'),
        title: $('email-campaign-title'),
        subtitle: $('email-campaign-subtitle'),
        duplicate: $('email-campaign-duplicate'),
        back: $('email-campaign-back'),
        form: $('email-campaign-form'),
        fromHint: $('email-campaign-from-hint'),
        lists: $('email-campaign-lists'),
        recipientCount: $('email-campaign-recipient-count'),
        htmlFile: $('email-campaign-html-file'),
        htmlUpload: $('email-campaign-html-upload'),
        unsubscribeCheck: $('email-campaign-unsubscribe-check'),
        htmlSize: $('email-campaign-html-size'),
        checks: $('email-campaign-checks'),
        formStatus: $('email-campaign-form-status'),
        save: $('email-campaign-save'),
        testForm: $('email-campaign-test-form'),
        testSubmit: $('email-campaign-test-submit'),
        testStatus: $('email-campaign-test-status'),
        previewWrap: $('email-preview-wrap'),
        previewFrame: $('email-preview-frame'),
        previewEmpty: $('email-preview-empty'),
        previewText: $('email-preview-text'),
        send: $('email-campaign-send'),
        sendModal: $('email-send-modal'),
        sendForm: $('email-send-form'),
        sendSummary: $('email-send-summary'),
        sendConfirmLabel: $('email-send-confirm-label'),
        sendStatus: $('email-send-form-status'),
        sendSubmit: $('email-send-submit'),
        progress: $('email-campaign-progress'),
        progressTitle: $('email-campaign-progress-title'),
        progressSubtitle: $('email-campaign-progress-subtitle'),
        progressBar: $('email-campaign-progress-bar'),
        retry: $('email-campaign-retry'),
        log: $('email-campaign-log'),
        logStatus: $('email-campaign-log-status'),
        logSearch: $('email-campaign-log-search'),
        logMore: $('email-campaign-log-more'),
        scheduleCard: $('email-campaign-schedule'),
        scheduleTitle: $('email-campaign-schedule-title'),
        scheduleSubtitle: $('email-campaign-schedule-subtitle'),
        unschedule: $('email-campaign-unschedule'),
        sendAtField: $('email-send-at-field'),
    };

    function formatSendTime(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        return date.toLocaleString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function toLocalInputValue(date) {
        const pad = (number) => String(number).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    const campaignState = {
        items: [],
        page: { page: 1, lastPage: 1 },
        search: '',
        status: 'all',
        options: null,
        current: null,
        dirty: false,
        previewMode: 'desktop',
        pollTimer: null,
        log: [],
        logPage: { page: 1, lastPage: 1 },
    };

    const campaignStatusLabels = { draft: 'Draft', scheduled: 'Scheduled', sending: 'Sending', sent: 'Sent' };
    const campaignStatusPill = { draft: 'pill outline', scheduled: 'pill', sending: 'pill', sent: 'pill success' };
    const mergeTagPattern = /\{\{\s*([a-zA-Z0-9_]+)\s*(?:\|([^}]*))?\}\}/g;

    async function loadCampaigns(append = false) {
        campaignDom.loadMore.disabled = true;
        if (!append) {
            campaignState.page = { page: 1, lastPage: 1 };
            resetTable(campaignDom.table);
            emptyRow(campaignDom.table, 'email-campaigns', 'Loading campaigns...', 7);
        }

        try {
            const page = append ? campaignState.page.page + 1 : 1;
            const query = buildQuery({ per_page: 20, page, search: campaignState.search, status: campaignState.status });
            const response = await api.get(`/api/email-campaigns${query}`);
            const items = response?.data?.data ?? [];
            campaignState.items = append ? [...campaignState.items, ...items] : items;
            campaignState.page = { page: response?.data?.current_page ?? page, lastPage: response?.data?.last_page ?? page };
            renderCampaigns();
        } catch (error) {
            resetTable(campaignDom.table);
            emptyRow(campaignDom.table, 'email-campaigns', 'Unable to load campaigns.', 7);
        } finally {
            setLoadMore(campaignDom.loadMore, campaignState.page);
        }
    }

    function renderCampaigns() {
        resetTable(campaignDom.table);

        if (!campaignState.items.length) {
            const filtered = campaignState.search || campaignState.status !== 'all';
            emptyRow(campaignDom.table, 'email-campaigns', filtered ? 'No campaigns match these filters.' : 'No campaigns yet. Click New campaign to create one.', 7);
            return;
        }

        campaignState.items.forEach((campaign) => {
            const editable = ['draft', 'scheduled'].includes(campaign.status);
            const recipients = editable
                ? `${Number(campaign.recipient_estimate || 0).toLocaleString('en-GB')}<small class="email-subtext">if sent now</small>`
                : `${Number(campaign.recipients_count || 0).toLocaleString('en-GB')}<small class="email-subtext">${Number(campaign.sent_count || 0).toLocaleString('en-GB')} sent${campaign.failed_count ? ` · ${campaign.failed_count} failed` : ''}${campaign.unsubscribed_count ? ` · ${campaign.unsubscribed_count} unsubscribed` : ''}</small>`;
            const row = document.createElement('div');
            row.className = 'table-row email-campaigns';
            row.innerHTML = `
                <span><button type="button" class="email-link" data-action="open" data-id="${campaign.id}">${escapeHtml(campaign.name)}</button></span>
                <span class="email-muted">${escapeHtml(campaign.subject || '--')}</span>
                <span>${escapeHtml((campaign.lists || []).map((list) => list.name).join(', ') || '--')}</span>
                <span><span class="${campaignStatusPill[campaign.status] || 'pill outline'}">${campaignStatusLabels[campaign.status] || escapeHtml(campaign.status)}</span></span>
                <span>${campaign.sent_at ? formatDate(campaign.sent_at) : (campaign.status === 'scheduled' && campaign.scheduled_at ? `<small class="email-subtext email-scheduled-at">Scheduled</small>${escapeHtml(formatSendTime(campaign.scheduled_at))}` : '--')}</span>
                <span>${recipients}</span>
                <div class="row-actions">
                    <button class="btn btn-outline btn-small" data-action="open" data-id="${campaign.id}">${editable ? 'Edit' : 'View'}</button>
                    <button class="btn btn-outline btn-small" data-action="duplicate" data-id="${campaign.id}">Duplicate</button>
                    ${editable ? `<button class="btn btn-outline btn-small" data-action="delete" data-id="${campaign.id}">Delete</button>` : ''}
                </div>
            `;
            campaignDom.table.appendChild(row);
        });
    }

    async function duplicateCampaign(id) {
        try {
            const response = await api.post(`/api/email-campaigns/${id}/duplicate`);
            showToast('Campaign duplicated.');
            openCampaign(response.data.data.id);
        } catch (error) {
            showToast(getErrorMessage(error, 'Unable to duplicate campaign.'), true);
        }
    }

    campaignDom.newButton.addEventListener('click', () => openCampaign(null));
    campaignDom.loadMore.addEventListener('click', () => loadCampaigns(true));
    campaignDom.search.addEventListener('input', debounce(() => {
        campaignState.search = campaignDom.search.value.trim();
        loadCampaigns();
    }));
    campaignDom.status.addEventListener('change', () => {
        campaignState.status = campaignDom.status.value;
        loadCampaigns();
    });

    campaignDom.table.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const campaign = campaignState.items.find((item) => Number(item.id) === Number(button.dataset.id));
        if (!campaign) return;

        if (button.dataset.action === 'open') openCampaign(campaign.id);
        if (button.dataset.action === 'duplicate') duplicateCampaign(campaign.id);
        if (button.dataset.action === 'delete') {
            if (!window.confirm(`Delete the draft “${campaign.name}”?`)) return;
            try {
                await api.delete(`/api/email-campaigns/${campaign.id}`);
                showToast('Campaign deleted.');
                await loadCampaigns();
            } catch (error) {
                showToast(getErrorMessage(error, 'Unable to delete campaign.'), true);
            }
        }
    });

    // ---- Campaign editor ----

    async function ensureCampaignOptions(refresh = false) {
        if (campaignState.options && !refresh) return campaignState.options;
        const response = await api.get('/api/email-campaigns/options');
        campaignState.options = response.data;
        return campaignState.options;
    }

    function openCampaign(id) {
        campaignState.current = id ? { id: Number(id) } : { id: null };
        setActiveView('email-campaign');
    }

    async function loadCampaignEditor() {
        const id = campaignState.current?.id ?? null;
        campaignDom.form.reset();
        campaignDom.testForm.reset();
        setFormStatus(campaignDom.formStatus, '');
        setFormStatus(campaignDom.testStatus, '');

        try {
            const options = await ensureCampaignOptions(true);
            const campaign = id
                ? (await api.get(`/api/email-campaigns/${id}`)).data.data
                : {
                    id: null,
                    status: 'draft',
                    name: '',
                    from_name: options.defaults.from_name || '',
                    from_email: options.defaults.from_email || '',
                    reply_to: options.defaults.reply_to || '',
                    list_ids: [],
                    problems: null,
                };
            campaignState.current = campaign;
            fillCampaignForm(campaign, options);
        } catch (error) {
            showToast(getErrorMessage(error, 'Unable to load campaign.'), true);
            setActiveView('email-marketing');
        }
    }

    function fillCampaignForm(campaign, options) {
        const form = campaignDom.form.elements;
        ['name', 'subject', 'preview_text', 'from_name', 'from_email', 'reply_to', 'html_body'].forEach((field) => {
            form[field].value = campaign[field] || '';
        });

        campaignDom.lists.innerHTML = options.lists.length
            ? options.lists.map((list) => `
                <label class="check-row email-check-row">
                    <input type="checkbox" name="list_ids" value="${list.id}" ${(campaign.list_ids || []).includes(list.id) ? 'checked' : ''}>
                    <span>${escapeHtml(list.name)} <small class="email-muted">${Number(list.subscribed_count || 0).toLocaleString('en-GB')} subscribed</small></span>
                </label>
            `).join('')
            : '<div class="form-hint">No lists yet. Create one on the Lists tab first.</div>';

        const domains = options.allowed_from_domains || [];
        campaignDom.fromHint.textContent = domains.length
            ? `The from email must be on ${domains.join(' or ')}.`
            : 'No sending domain is configured yet (MARKETING_ALLOWED_FROM_DOMAINS).';

        const editable = ['draft', 'scheduled'].includes(campaign.status);
        campaignDom.form.querySelectorAll('input, textarea, select, button').forEach((element) => {
            element.disabled = !editable;
        });
        campaignDom.htmlUpload.disabled = !editable;
        campaignDom.testForm.hidden = !editable;
        campaignDom.duplicate.hidden = !campaign.id;
        campaignDom.title.textContent = campaign.name || 'New campaign';
        campaignDom.subtitle.textContent = editable
            ? (campaign.id ? `${campaignStatusLabels[campaign.status]}${campaign.status === 'scheduled' ? ` for ${formatSendTime(campaign.scheduled_at)}` : ''} · last saved ${formatDate(campaign.updated_at)}` : 'Not saved yet')
            : `${campaignStatusLabels[campaign.status]}${campaign.sent_at ? ` ${formatDate(campaign.sent_at)}` : ''} to ${Number(campaign.recipients_count || 0).toLocaleString('en-GB')} people · read only`;

        renderChecks(campaign.problems);
        campaignState.dirty = false;
        campaignDom.save.hidden = !editable;
        campaignDom.send.hidden = !editable;
        campaignDom.send.textContent = campaign.status === 'scheduled' ? 'Send now or reschedule…' : 'Send campaign…';
        showSchedule(campaign);
        updateSendButton();
        showProgress(campaign);
        refreshRecipientCount();
        refreshHtmlMeta();
        refreshPreview();
    }

    function selectedListIds() {
        return [...campaignDom.lists.querySelectorAll('input[name="list_ids"]:checked')].map((input) => Number(input.value));
    }

    function renderChecks(problems) {
        if (problems === null || problems === undefined) {
            campaignDom.checks.innerHTML = '<li class="email-check-pending">Save the draft to check it.</li>';
            return;
        }
        campaignDom.checks.innerHTML = problems.length
            ? problems.map((problem) => `<li class="email-check-problem">${escapeHtml(problem)}</li>`).join('')
            : '<li class="email-check-ok">Ready to send.</li>';
    }

    const refreshRecipientCount = debounce(async () => {
        const ids = selectedListIds();
        if (!ids.length) {
            campaignDom.recipientCount.textContent = 'Choose one or more lists.';
            return;
        }
        try {
            const response = await api.get(`/api/email-campaigns/recipient-count${'?' + ids.map((id) => `list_ids[]=${id}`).join('&')}`);
            const count = Number(response.data.count || 0);
            campaignDom.recipientCount.textContent = `${count.toLocaleString('en-GB')} ${count === 1 ? 'person' : 'people'} would receive this. Each address counts once, and unsubscribed or bounced people are left out.`;
        } catch (error) {
            campaignDom.recipientCount.textContent = 'Unable to count recipients.';
        }
    }, 250);

    function refreshHtmlMeta() {
        const html = campaignDom.form.elements.html_body.value;
        const hasUnsubscribe = /\{\{\s*unsubscribe_url\s*(\|[^}]*)?\}\}/i.test(html);
        campaignDom.unsubscribeCheck.className = html.trim() === '' ? '' : (hasUnsubscribe ? 'email-check-ok' : 'email-check-problem');
        campaignDom.unsubscribeCheck.textContent = html.trim() === ''
            ? ''
            : (hasUnsubscribe ? 'Unsubscribe link found' : 'Missing {{unsubscribe_url}}: this email can\'t be sent to a list without it');
        const kb = new Blob([html]).size / 1024;
        campaignDom.htmlSize.textContent = html.trim() === '' ? '' : `${kb.toFixed(1)} KB${kb > 100 ? ' · over 100 KB, Gmail may cut it short' : ''}`;
    }

    function sampleMerge(content) {
        const values = {
            first_name: '',
            last_name: '',
            email: 'name@example.com',
            unsubscribe_url: '#unsubscribe-link',
            view_in_browser_url: '#view-in-browser-link',
        };
        return content.replace(mergeTagPattern, (match, tag, fallback) => {
            const key = tag.toLowerCase();
            if (!(key in values)) return match;
            const value = values[key] || (fallback !== undefined ? fallback.trim() : '');
            return escapeHtml(value);
        });
    }

    function withPreviewText(html, previewText) {
        const text = (previewText || '').trim();
        if (!text) return html;
        const hidden = `<div style="display:none;max-height:0;overflow:hidden;">${escapeHtml(text)}</div>`;
        return /<body\b[^>]*>/i.test(html) ? html.replace(/(<body\b[^>]*>)/i, `$1${hidden}`) : hidden + html;
    }

    const refreshPlainText = debounce(async () => {
        try {
            const response = await api.post('/api/email-campaigns/plain-text', { html: campaignDom.form.elements.html_body.value });
            campaignDom.previewText.textContent = response.data.text || '(No text found in the HTML.)';
        } catch (error) {
            campaignDom.previewText.textContent = getErrorMessage(error, 'Unable to build the plain-text version.');
        }
    }, 400);

    const refreshPreview = debounce(() => {
        const form = campaignDom.form.elements;
        const html = form.html_body.value;
        const isText = campaignState.previewMode === 'text';

        campaignDom.previewWrap.hidden = isText;
        campaignDom.previewText.hidden = !isText;
        campaignDom.previewWrap.classList.toggle('is-mobile', campaignState.previewMode === 'mobile');
        campaignDom.previewEmpty.hidden = html.trim() !== '';

        if (isText) {
            refreshPlainText();
            return;
        }

        // Preview only: links open in a new tab. The saved HTML is never changed.
        let preview = sampleMerge(withPreviewText(html, form.preview_text.value));
        preview = /<head\b[^>]*>/i.test(preview) ? preview.replace(/(<head\b[^>]*>)/i, '$1<base target="_blank">') : `<base target="_blank">${preview}`;
        campaignDom.previewFrame.srcdoc = html.trim() === '' ? '' : preview;
    }, 250);

    document.querySelectorAll('input[name="email-preview-mode"]').forEach((input) => {
        input.addEventListener('change', () => {
            campaignState.previewMode = input.value;
            refreshPreview();
        });
    });

    campaignDom.form.addEventListener('input', (event) => {
        campaignState.dirty = true;
        updateSendButton();
        if (event.target.name === 'html_body') refreshHtmlMeta();
        if (['html_body', 'preview_text'].includes(event.target.name)) refreshPreview();
        if (event.target.name === 'name') campaignDom.title.textContent = event.target.value || 'New campaign';
    });

    campaignDom.lists.addEventListener('change', () => {
        campaignState.dirty = true;
        updateSendButton();
        refreshRecipientCount();
    });

    campaignDom.htmlUpload.addEventListener('click', () => campaignDom.htmlFile.click());

    campaignDom.htmlFile.addEventListener('change', async () => {
        const file = campaignDom.htmlFile.files[0];
        campaignDom.htmlFile.value = '';
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            showToast('That file is over 2 MB. Email HTML should be much smaller.', true);
            return;
        }

        const textarea = campaignDom.form.elements.html_body;
        if (textarea.value.trim() !== '' && !window.confirm(`Replace the current HTML with ${file.name}?`)) return;

        try {
            textarea.value = await file.text();
            campaignState.dirty = true;
            refreshHtmlMeta();
            refreshPreview();
            showToast(`Loaded ${file.name}. Save the draft to keep it.`);
        } catch (error) {
            showToast('Unable to read that file.', true);
        }
    });

    function campaignPayload() {
        const form = campaignDom.form.elements;
        const value = (field) => form[field].value.trim() || null;

        return {
            name: form.name.value.trim(),
            subject: value('subject'),
            preview_text: value('preview_text'),
            from_name: value('from_name'),
            from_email: value('from_email'),
            reply_to: value('reply_to'),
            // Sent as-is: no trimming, so the HTML is stored exactly as written.
            html_body: form.html_body.value === '' ? null : form.html_body.value,
            list_ids: selectedListIds(),
        };
    }

    async function saveCampaign({ quiet = false } = {}) {
        const payload = campaignPayload();
        if (!payload.name) {
            setFormStatus(campaignDom.formStatus, 'Give the campaign a name.', true);
            campaignDom.form.elements.name.focus();
            return false;
        }

        campaignDom.save.disabled = true;
        try {
            const id = campaignState.current?.id;
            const response = id
                ? await api.put(`/api/email-campaigns/${id}`, payload)
                : await api.post('/api/email-campaigns', payload);
            const campaign = response.data.data;
            const isNew = !id;
            campaignState.current = campaign;
            campaignState.dirty = false;
            renderChecks(campaign.problems);
            updateSendButton();
            campaignDom.duplicate.hidden = false;
            campaignDom.subtitle.textContent = `${campaignStatusLabels[campaign.status]} · last saved ${formatDate(campaign.updated_at)}`;
            setFormStatus(campaignDom.formStatus, '');
            if (isNew) window.history.replaceState(null, '', `#/email-campaign/${campaign.id}`);
            if (!quiet) showToast('Draft saved.');
            return true;
        } catch (error) {
            setFormStatus(campaignDom.formStatus, getErrorMessage(error, 'Unable to save campaign.'), true);
            return false;
        } finally {
            campaignDom.save.disabled = false;
        }
    }

    campaignDom.form.addEventListener('submit', (event) => {
        event.preventDefault();
        saveCampaign();
    });

    campaignDom.testForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const emails = campaignDom.testForm.elements.emails.value.trim();
        if (!emails) {
            setFormStatus(campaignDom.testStatus, 'Enter at least one email address.', true);
            return;
        }

        campaignDom.testSubmit.disabled = true;
        setFormStatus(campaignDom.testStatus, 'Saving and sending…');
        try {
            if ((campaignState.dirty || !campaignState.current?.id) && !(await saveCampaign({ quiet: true }))) {
                setFormStatus(campaignDom.testStatus, 'Fix the details above before sending a test.', true);
                return;
            }
            const response = await api.post(`/api/email-campaigns/${campaignState.current.id}/test`, { emails });
            setFormStatus(campaignDom.testStatus, response.data.message);
            showToast('Test email sent.');
        } catch (error) {
            setFormStatus(campaignDom.testStatus, getErrorMessage(error, 'Unable to send the test.'), true);
        } finally {
            campaignDom.testSubmit.disabled = false;
        }
    });

    function updateSendButton() {
        const campaign = campaignState.current;
        const ready = Boolean(campaign?.id) && Array.isArray(campaign?.problems) && campaign.problems.length === 0;
        campaignDom.send.disabled = !ready;
        campaignDom.send.title = !campaign?.id
            ? 'Save the draft first.'
            : (!ready ? 'Fix the items listed above first.' : (campaignState.dirty ? 'Your changes will be saved before sending.' : ''));
    }

    campaignDom.send.addEventListener('click', async () => {
        if (campaignState.dirty && !(await saveCampaign({ quiet: true }))) return;
        if (campaignState.current?.problems?.length) {
            showToast('Fix the items under "Ready to send?" first.', true);
            return;
        }

        try {
            const { data } = await api.get(`/api/email-campaigns/${campaignState.current.id}/send-summary`);
            if (data.problems.length) {
                renderChecks(data.problems);
                showToast(data.problems[0], true);
                return;
            }
            const campaign = campaignState.current;
            const minutes = Math.max(1, Math.ceil(data.count / data.rate_per_minute));
            campaignDom.sendSummary.innerHTML = `
                <div class="detail-meta">
                    <div><div class="meta-label">Recipients</div><div class="meta-value">${Number(data.count).toLocaleString('en-GB')} ${data.count === 1 ? 'person' : 'people'}</div></div>
                    <div><div class="meta-label">Lists</div><div class="meta-value">${escapeHtml(data.lists.join(', '))}</div></div>
                    <div><div class="meta-label">Subject</div><div class="meta-value">${escapeHtml(campaign.subject || '')}</div></div>
                    <div><div class="meta-label">From</div><div class="meta-value">${escapeHtml(`${campaign.from_name} <${campaign.from_email}>`)}</div></div>
                </div>
                <div class="form-hint">Sent in batches of ${data.rate_per_minute} a minute, so this takes about ${minutes} ${minutes === 1 ? 'minute' : 'minutes'}. Each person gets their own copy with their own unsubscribe link. Anyone who unsubscribes before their turn is skipped.</div>
            `;
            campaignState.sendCount = data.count;
            campaignDom.sendForm.reset();
            const soonest = new Date(Date.now() + 10 * 60 * 1000);
            const at = campaignDom.sendForm.elements.scheduled_at;
            at.min = toLocalInputValue(soonest);
            at.value = campaign.scheduled_at ? toLocalInputValue(new Date(campaign.scheduled_at)) : '';
            campaignDom.sendForm.elements.send_when.value = campaign.status === 'scheduled' ? 'later' : 'now';
            updateSendMode(data.count);
            setFormStatus(campaignDom.sendStatus, '');
            campaignDom.sendSubmit.disabled = data.count === 0;
            if (data.count === 0) setFormStatus(campaignDom.sendStatus, 'Nobody on these lists can be emailed.', true);
            openModal(campaignDom.sendModal);
        } catch (error) {
            showToast(getErrorMessage(error, 'Unable to prepare the send.'), true);
        }
    });

    campaignDom.sendForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!campaignDom.sendForm.elements.confirmed.checked) {
            setFormStatus(campaignDom.sendStatus, 'Tick the box to confirm.', true);
            return;
        }

        const later = campaignDom.sendForm.elements.send_when.value === 'later';
        const localValue = campaignDom.sendForm.elements.scheduled_at.value;
        if (later && !localValue) {
            setFormStatus(campaignDom.sendStatus, 'Choose when to send it.', true);
            return;
        }

        campaignDom.sendSubmit.disabled = true;
        try {
            const { data } = later
                ? await api.post(`/api/email-campaigns/${campaignState.current.id}/schedule`, { confirmed: true, scheduled_at: new Date(localValue).toISOString() })
                : await api.post(`/api/email-campaigns/${campaignState.current.id}/send`, { confirmed: true });
            closeModal(campaignDom.sendModal);
            showToast(data.message || 'Sending started.');
            await loadCampaignEditor();
        } catch (error) {
            setFormStatus(campaignDom.sendStatus, getErrorMessage(error, 'Unable to send the campaign.'), true);
        } finally {
            campaignDom.sendSubmit.disabled = false;
        }
    });

    function updateSendMode(count = campaignState.sendCount) {
        const later = campaignDom.sendForm.elements.send_when.value === 'later';
        const people = `${Number(count || 0).toLocaleString('en-GB')} ${count === 1 ? 'person' : 'people'}`;
        campaignDom.sendAtField.hidden = !later;
        campaignDom.sendSubmit.textContent = later ? 'Schedule' : 'Send now';
        $('email-send-modal-subtitle').textContent = later
            ? 'Check everything below. You can edit or cancel it any time before it sends.'
            : 'Check everything below. Once it starts, it can\'t be stopped or changed.';
        const when = campaignDom.sendForm.elements.scheduled_at.value;
        campaignDom.sendConfirmLabel.textContent = later
            ? `I'm ready to schedule this${when ? ` for ${formatSendTime(when)}` : ''}. It currently reaches ${people}.`
            : `I'm ready to send this to ${people}.`;
    }

    campaignDom.sendForm.addEventListener('change', (event) => {
        if (['send_when', 'scheduled_at'].includes(event.target.name)) updateSendMode();
    });

    function showSchedule(campaign) {
        const scheduled = campaign.status === 'scheduled' && campaign.scheduled_at;
        const failed = !scheduled && campaign.last_error && ['draft'].includes(campaign.status);
        campaignDom.scheduleCard.hidden = !(scheduled || failed);
        campaignDom.scheduleCard.classList.toggle('is-problem', Boolean(failed));
        campaignDom.unschedule.hidden = !scheduled;
        if (scheduled) {
            campaignDom.scheduleTitle.textContent = `Scheduled for ${formatSendTime(campaign.scheduled_at)}`;
            campaignDom.scheduleSubtitle.textContent = 'It will be checked again and sent automatically. You can still edit and save it until then.';
        } else if (failed) {
            campaignDom.scheduleTitle.textContent = 'The scheduled send didn\'t go out';
            campaignDom.scheduleSubtitle.textContent = campaign.last_error;
        }
    }

    campaignDom.unschedule.addEventListener('click', async () => {
        if (!window.confirm('Cancel the scheduled send? The campaign will go back to being a draft.')) return;
        try {
            const { data } = await api.post(`/api/email-campaigns/${campaignState.current.id}/unschedule`);
            showToast(data.message);
            await loadCampaignEditor();
        } catch (error) {
            showToast(getErrorMessage(error, 'Unable to cancel the schedule.'), true);
        }
    });

    function stopPolling() {
        if (campaignState.pollTimer) window.clearInterval(campaignState.pollTimer);
        campaignState.pollTimer = null;
    }

    function editorVisible() {
        return document.querySelector('.view[data-view="email-campaign"]')?.classList.contains('active');
    }

    function showProgress(campaign) {
        stopPolling();
        const started = ['sending', 'sent'].includes(campaign.status);
        campaignDom.progress.hidden = !started;
        if (!started) return;

        refreshProgress();
        loadLog();
        if (campaign.status === 'sending') {
            campaignState.pollTimer = window.setInterval(() => {
                if (!editorVisible()) {
                    stopPolling();
                    return;
                }
                refreshProgress();
                // Keep the log's first page current while sending, without flashing "Loading".
                if (campaignState.logPage.page === 1) loadLog(false, true);
            }, 3000);
        }
    }

    async function refreshProgress() {
        const id = campaignState.current?.id;
        if (!id) return;
        try {
            const { data } = await api.get(`/api/email-campaigns/${id}/progress`);
            const done = data.sent + data.failed + data.skipped;
            const percent = data.recipients ? Math.round((done / data.recipients) * 100) : 0;
            const bar = campaignDom.progressBar;
            bar.querySelector('span').style.width = `${percent}%`;
            bar.setAttribute('aria-valuenow', String(percent));
            campaignDom.progress.querySelectorAll('[data-email-stat]').forEach((element) => {
                element.textContent = Number(data[element.dataset.emailStat] || 0).toLocaleString('en-GB');
            });

            if (data.status === 'sending') {
                const minutes = Math.ceil(data.pending / Math.max(1, campaignState.options?.rate_per_minute || 60));
                campaignDom.progressTitle.textContent = `Sending… ${done.toLocaleString('en-GB')} of ${Number(data.recipients).toLocaleString('en-GB')}`;
                campaignDom.progressSubtitle.textContent = data.pending
                    ? `Started ${formatDate(data.started_at)} · about ${minutes} ${minutes === 1 ? 'minute' : 'minutes'} left. You can leave this page; sending carries on in the background.`
                    : 'Finishing up…';
                bar.classList.add('is-active');
            } else {
                campaignDom.progressTitle.textContent = `Sent to ${data.sent.toLocaleString('en-GB')} ${data.sent === 1 ? 'person' : 'people'}`;
                campaignDom.progressSubtitle.textContent = `Finished ${formatDate(data.sent_at)}${data.failed ? `. ${data.failed} couldn't be sent; check the delivery log below.` : '.'}`;
                bar.classList.remove('is-active');
                if (campaignState.pollTimer) {
                    stopPolling();
                    campaignState.current.status = data.status;
                    campaignDom.subtitle.textContent = `Sent ${formatDate(data.sent_at)} · read only`;
                    loadLog();
                }
            }
            campaignDom.retry.hidden = !(data.status === 'sent' && data.failed > 0);
        } catch (error) {
            // Keep the last numbers on screen; the next poll will try again.
        }
    }

    const logStatusLabels = { pending: 'Waiting', sending: 'Sending', sent: 'Sent', failed: 'Failed', skipped: 'Skipped' };
    const logStatusPill = { sent: 'pill success', failed: 'pill email-pill-bounced', skipped: 'pill outline', pending: 'pill outline', sending: 'pill' };

    async function loadLog(append = false, quiet = false) {
        const id = campaignState.current?.id;
        if (!id) return;
        campaignDom.logMore.disabled = true;
        if (!append && !quiet) {
            campaignState.logPage = { page: 1, lastPage: 1 };
            resetTable(campaignDom.log);
            emptyRow(campaignDom.log, 'email-campaign-log', 'Loading…', 4);
        }
        try {
            const page = append ? campaignState.logPage.page + 1 : 1;
            const query = buildQuery({ per_page: 25, page, status: campaignDom.logStatus.value, search: campaignDom.logSearch.value.trim() });
            const { data } = await api.get(`/api/email-campaigns/${id}/recipients${query}`);
            campaignState.log = append ? [...campaignState.log, ...(data.data || [])] : (data.data || []);
            campaignState.logPage = { page: data.current_page ?? page, lastPage: data.last_page ?? page };
            renderLog();
        } catch (error) {
            resetTable(campaignDom.log);
            emptyRow(campaignDom.log, 'email-campaign-log', 'Unable to load the delivery log.', 4);
        } finally {
            setLoadMore(campaignDom.logMore, campaignState.logPage);
        }
    }

    function renderLog() {
        resetTable(campaignDom.log);
        if (!campaignState.log.length) {
            emptyRow(campaignDom.log, 'email-campaign-log', 'Nobody matches these filters.', 4);
            return;
        }
        campaignState.log.forEach((recipient) => {
            const name = [recipient.first_name, recipient.last_name].filter(Boolean).join(' ');
            const details = recipient.unsubscribed_at
                ? `Unsubscribed ${formatDate(recipient.unsubscribed_at)}`
                : (recipient.error || (recipient.attempts > 1 ? `Sent after ${recipient.attempts} attempts` : ''));
            const row = document.createElement('div');
            row.className = 'table-row email-campaign-log';
            row.innerHTML = `
                <span><strong class="email-address">${escapeHtml(recipient.email)}</strong>${name ? `<small class="email-subtext">${escapeHtml(name)}</small>` : ''}</span>
                <span><span class="${logStatusPill[recipient.status] || 'pill outline'}">${logStatusLabels[recipient.status] || escapeHtml(recipient.status)}</span></span>
                <span>${recipient.sent_at ? formatDate(recipient.sent_at) : '--'}</span>
                <span class="email-muted">${escapeHtml(details || '--')}</span>
            `;
            campaignDom.log.appendChild(row);
        });
    }

    campaignDom.logStatus.addEventListener('change', () => loadLog());
    campaignDom.logSearch.addEventListener('input', debounce(() => loadLog()));
    campaignDom.logMore.addEventListener('click', () => loadLog(true));

    campaignDom.retry.addEventListener('click', async () => {
        if (!window.confirm('Try sending again to everyone whose email failed?')) return;
        try {
            const { data } = await api.post(`/api/email-campaigns/${campaignState.current.id}/retry-failed`);
            showToast(data.message);
            await loadCampaignEditor();
        } catch (error) {
            showToast(getErrorMessage(error, 'Unable to retry.'), true);
        }
    });

    campaignDom.back.addEventListener('click', () => {
        if (campaignState.dirty && !window.confirm('You have unsaved changes. Leave without saving?')) return;
        campaignState.dirty = false;
        setActiveView('email-marketing');
    });

    campaignDom.duplicate.addEventListener('click', () => {
        if (campaignState.dirty && !window.confirm('You have unsaved changes. Duplicate the last saved version anyway?')) return;
        if (campaignState.current?.id) duplicateCampaign(campaignState.current.id);
    });

    window.addEventListener('beforeunload', (event) => {
        if (campaignState.dirty) event.preventDefault();
    });

    return {
        onView(view) {
            if (view === 'email-marketing') showTab(activeTab);
            if (view === 'email-list-detail') refreshDetail();
            if (view === 'email-campaign') loadCampaignEditor();
        },
        openList,
        openCampaign,
        currentListId: () => state.currentList?.id ?? null,
        currentCampaignId: () => campaignState.current?.id ?? null,
    };
}
