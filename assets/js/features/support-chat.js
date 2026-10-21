/**
 * Customer support conversation: polls for new messages, Ctrl/Cmd+Enter sends,
 * shows the names of selected attachments.
 */
import { getJson } from '../core/http.js';
import { t } from '../core/i18n.js';

const POLL_INTERVAL_MS = 4000;

function renderMessage(thread, message) {
    const own = message.senderType === 'customer';
    const article = document.createElement('article');
    article.dataset.messageId = String(message.id);
    article.className = `flex ${own ? 'justify-end' : 'justify-start'}`;

    const bubble = document.createElement('div');
    bubble.className = own
        ? 'max-w-[85%] rounded-2xl rounded-br-md bg-primary-600 px-4 py-3 text-white'
        : 'max-w-[85%] rounded-2xl rounded-bl-md border border-line bg-panel px-4 py-3';

    const meta = document.createElement('p');
    meta.className = `mb-1 text-xs ${own ? 'text-white/75' : 'text-fg-subtle'}`;
    meta.textContent = `${own ? t('support.sender.you') : t('support.sender.support')} · ${new Date(message.createdAt).toLocaleString(document.documentElement.lang || undefined)}`;

    const body = document.createElement('p');
    body.className = 'whitespace-pre-wrap text-sm';
    body.textContent = message.body;

    bubble.append(meta, body);

    (message.attachments || []).forEach((attachment) => {
        const link = document.createElement('a');
        link.href = thread.dataset.attachmentUrl.replace('__ID__', String(attachment.id));
        link.className = `mt-2 block text-xs underline ${own ? 'text-white' : 'text-primary-600'}`;
        link.textContent = attachment.name;
        bubble.appendChild(link);
    });

    article.appendChild(bubble);
    thread.appendChild(article);
}

export function init() {
    const thread = document.querySelector('[data-support-thread]');
    if (!thread) {
        return;
    }

    const form = document.querySelector('[data-support-form]');
    const body = form?.querySelector('textarea[name="body"]');
    const files = form?.querySelector('input[type="file"]');
    const fileNames = form?.querySelector('[data-file-names]');

    let lastId = Math.max(0, ...Array.from(thread.querySelectorAll('[data-message-id]'), (el) => Number(el.dataset.messageId) || 0));
    thread.scrollTop = thread.scrollHeight;

    body?.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    files?.addEventListener('change', () => {
        if (fileNames) {
            fileNames.textContent = Array.from(files.files || [], (file) => file.name).join(', ');
        }
    });

    const poll = async () => {
        if (document.hidden) {
            return;
        }
        try {
            const { ok, data } = await getJson(`${thread.dataset.pollUrl}?afterId=${lastId}`);
            if (!ok || !Array.isArray(data?.messages) || data.messages.length === 0) {
                return;
            }
            const atBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 80;
            data.messages.forEach((message) => {
                if (thread.querySelector(`[data-message-id="${message.id}"]`)) {
                    return;
                }
                renderMessage(thread, message);
                lastId = Math.max(lastId, Number(message.id) || 0);
            });
            if (atBottom) {
                thread.scrollTop = thread.scrollHeight;
            }
        } catch {
            /* network hiccup — try again on the next tick */
        }
    };

    setInterval(poll, POLL_INTERVAL_MS);
}
