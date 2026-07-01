document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});

function refreshQuestionEditor(select) {
  const root = select.closest('.question-edit-card,.question-editor') || document;
  const type = select.value;
  root.querySelectorAll('.question-type-panel').forEach(panel => {
    const name = panel.dataset.panel;
    const show = type === 'mixed' ||
      (type === 'mc' && name === 'mc') ||
      (type === 'mc' && name === 'mc-answer') ||
      (type === 'tf' && name === 'tf') ||
      ((type === 'sa' || type === 'essay') && name === 'text');
    panel.style.display = show ? '' : 'none';
    panel.querySelectorAll('input, select, textarea').forEach(field => {
      field.disabled = !show;
    });
  });
}

document.querySelectorAll('[data-question-type]').forEach(select => {
  refreshQuestionEditor(select);
  select.addEventListener('change', () => refreshQuestionEditor(select));
});

function renumberPreviewQuestions(form) {
  form.querySelectorAll('.question-edit-card').forEach((card, index) => {
    const title = card.querySelector('.question-edit-head strong');
    if (title) title.textContent = `Câu ${index + 1}`;
    card.querySelectorAll('[name]').forEach(field => {
      field.name = field.name.replace(/q\[\d+\]/g, `q[${index}]`);
    });
    card.querySelectorAll('[data-option-name-template]').forEach(list => {
      list.dataset.optionNameTemplate = list.dataset.optionNameTemplate.replace(/q\[\d+\]/g, `q[${index}]`);
    });
    card.querySelectorAll('[data-answer-select]').forEach(list => {
      list.dataset.answerSelect = list.dataset.answerSelect.replace(/q\[\d+\]/g, `q[${index}]`);
    });
  });
}

function previewQuestionTemplate(index) {
  const mcOptions = ['A', 'B', 'C', 'D'].map(label => `
          <div class="option-row" data-option-label="${label}">
            <label>Đáp án ${label}</label>
            <input name="q[${index}][options][${label}]" value="">
          </div>`).join('');
  const mcAnswerOptions = ['A', 'B', 'C', 'D'].map(label => `<option value="${label}">${label}</option>`).join('');
  const tfRows = ['a', 'b', 'c', 'd'].map(label => `
          <div class="option-row">
            <label>${label}</label>
            <input name="q[${index}][tf_items][${label}][content]" value="">
            <select name="q[${index}][tf_items][${label}][answer]">
              <option value="true">Đúng</option>
              <option value="false">Sai</option>
            </select>
          </div>`).join('');
  return `
    <div class="question-edit-card">
      <div class="question-edit-head">
        <strong>Câu ${index + 1}</strong>
        <select name="q[${index}][type]" data-question-type>
          <option value="mixed">Tổng hợp</option>
          <option value="mc" selected>Trắc nghiệm</option>
          <option value="tf">Đúng/Sai</option>
          <option value="sa">Trả lời ngắn</option>
          <option value="essay">Tự luận</option>
        </select>
        <select name="q[${index}][difficulty]">
          <option value="easy">Nhận biết</option>
          <option value="medium" selected>Thông hiểu</option>
          <option value="hard">Vận dụng</option>
          <option value="unknown">Không rõ</option>
        </select>
        <button class="btn danger" type="button" data-remove-preview-question><span class="material-symbols-outlined">delete</span> Xóa câu</button>
      </div>
      <input type="hidden" name="q[${index}][image_path]" value="">
      <div class="question-content-field">
        <label>Nội dung</label>
        <textarea name="q[${index}][content]"></textarea>
      </div>
      <div class="grid grid-2 question-type-panel" data-panel="mc">
        <div data-option-list data-option-name-template="q[${index}][options][__LABEL__]" data-answer-select="select[name='q[${index}][answer]']">
          ${mcOptions}
        </div>
        <button class="btn ghost" type="button" data-add-option><span class="material-symbols-outlined">add_circle</span> Thêm đáp án</button>
      </div>
      <div class="question-type-panel" data-panel="tf">
        <label>Đáp án Đúng/Sai</label>
        ${tfRows}
      </div>
      <div class="question-type-panel" data-panel="mc-answer">
        <label>Đáp án đúng</label>
        <select name="q[${index}][answer]">${mcAnswerOptions}</select>
      </div>
      <div class="question-type-panel" data-panel="text">
        <label>Đáp án gợi ý / đáp án ngắn</label>
        <input name="q[${index}][answer_text]" value="" placeholder="Đáp án tự luận hoặc trả lời ngắn">
      </div>
      <label>Giải thích</label>
      <textarea name="q[${index}][explanation]"></textarea>
    </div>`;
}

function nextOptionLabel(list) {
  const used = Array.from(list.querySelectorAll('[data-option-label]'))
    .map(row => (row.dataset.optionLabel || '').toUpperCase())
    .filter(Boolean);
  for (let code = 65; code <= 90; code++) {
    const label = String.fromCharCode(code);
    if (!used.includes(label)) return label;
  }
  return '';
}

function addOptionRow(list) {
  const label = nextOptionLabel(list);
  if (!label) return;
  const template = list.dataset.optionNameTemplate || 'options[__LABEL__]';
  const row = document.createElement('div');
  row.className = 'option-row';
  row.dataset.optionLabel = label;
  row.innerHTML = `<label>Đáp án ${label}</label><input name="${template.replace('__LABEL__', label)}" value="">`;
  list.appendChild(row);

  const root = list.closest('.question-edit-card,.question-editor') || document;
  const answerSelect = root.querySelector(list.dataset.answerSelect || 'select[name="answer"]');
  if (answerSelect && !Array.from(answerSelect.options).some(option => option.value === label)) {
    const option = document.createElement('option');
    option.value = label;
    option.textContent = label;
    answerSelect.appendChild(option);
    answerSelect.value = label;
  }
  row.querySelector('input')?.focus();
}

document.addEventListener('click', e => {
  const button = e.target.closest('[data-add-option]');
  if (!button) return;
  e.preventDefault();
  const panel = button.closest('.question-type-panel') || button.parentElement;
  const list = panel?.querySelector('[data-option-list]');
  if (list) addOptionRow(list);
});

document.querySelectorAll('[data-add-preview-question]').forEach(button => {
  button.addEventListener('click', () => {
    const form = button.closest('form');
    const cards = form ? form.querySelectorAll('.question-edit-card') : [];
    if (!form) return;
    const wrap = document.createElement('div');
    wrap.innerHTML = previewQuestionTemplate(cards.length).trim();
    const card = wrap.firstElementChild;
    const saveButton = form.querySelector('button[name="save_preview"]');
    if (saveButton) saveButton.before(card);
    else form.appendChild(card);
    renumberPreviewQuestions(form);
    card.querySelectorAll('[data-question-type]').forEach(select => {
      refreshQuestionEditor(select);
      select.addEventListener('change', () => refreshQuestionEditor(select));
    });
    card.querySelector('textarea[name$="[content]"]')?.focus();
  });
});

document.addEventListener('click', e => {
  const button = e.target.closest('[data-remove-preview-question]');
  if (!button) return;
  e.preventDefault();
  const card = button.closest('.question-edit-card');
  const form = button.closest('form');
  if (!card || !form) return;
  card.remove();
  renumberPreviewQuestions(form);
});

document.querySelectorAll('select[data-lesson-filter]').forEach(lessonSelect => {
  const key = lessonSelect.dataset.lessonFilter;
  const subjectSelect = document.querySelector(`select[data-subject-filter="${key}"]`);
  if (!subjectSelect) return;
  const newLessonOption = Array.from(lessonSelect.options).find(option => option.dataset.newLessonOption)?.cloneNode(true);
  const allOptions = Array.from(lessonSelect.options)
    .filter(option => !option.dataset.newLessonOption)
    .map(option => option.cloneNode(true));

  function refreshNewLessonField() {
    const field = lessonSelect.closest('div')?.querySelector('[data-new-lesson-field]');
    const input = field?.querySelector('input');
    const isNew = lessonSelect.value === '__new__';
    if (field) field.style.display = isNew ? '' : 'none';
    if (input) {
      input.required = isNew;
      if (!isNew) input.value = '';
    }
  }

  function refreshLessons() {
    const subjectId = subjectSelect.value;
    const currentValue = lessonSelect.value;
    const matches = allOptions.filter(option => option.dataset.subjectId === subjectId);
    lessonSelect.innerHTML = '';

    if (!matches.length && !newLessonOption) {
      const empty = document.createElement('option');
      empty.value = '';
      empty.textContent = lessonSelect.dataset.emptyLabel || 'Chua co bai hoc phu hop';
      lessonSelect.appendChild(empty);
      lessonSelect.value = '';
      lessonSelect.disabled = true;
      refreshNewLessonField();
      return;
    }

    matches.forEach(option => lessonSelect.appendChild(option.cloneNode(true)));
    if (newLessonOption) lessonSelect.appendChild(newLessonOption.cloneNode(true));
    lessonSelect.disabled = false;
    if (matches.some(option => option.value === currentValue)) lessonSelect.value = currentValue;
    else if (currentValue === '__new__' && newLessonOption) lessonSelect.value = '__new__';
    refreshNewLessonField();
  }

  refreshLessons();
  subjectSelect.addEventListener('change', refreshLessons);
  lessonSelect.addEventListener('change', refreshNewLessonField);
});

function refreshExamMode(select) {
  const mode = select.value || 'mixed';
  const table = document.querySelector('[data-exam-builder-table]');
  if (!table) return;

  table.querySelectorAll('[data-exam-type]').forEach(cell => {
    const active = mode === 'mixed' || cell.dataset.examType === mode;
    cell.classList.toggle('exam-type-disabled', !active);
    cell.querySelectorAll('input').forEach(input => {
      input.disabled = !active;
      if (!active) input.value = '0';
    });
  });
}

document.querySelectorAll('[data-exam-mode]').forEach(select => {
  refreshExamMode(select);
  select.addEventListener('change', () => refreshExamMode(select));
});

document.querySelectorAll('[data-fill-all-questions]').forEach(button => {
  button.addEventListener('click', () => {
    document.querySelectorAll('[data-exam-builder-table] input[type="number"]').forEach(input => {
      if (!input.disabled) input.value = input.max || '0';
    });
  });
});

document.querySelectorAll('[data-clear-all-questions]').forEach(button => {
  button.addEventListener('click', () => {
    document.querySelectorAll('[data-exam-builder-table] input[type="number"]').forEach(input => {
      input.value = '0';
    });
  });
});

document.querySelectorAll('[data-chart-tabs]').forEach(tabGroup => {
  tabGroup.addEventListener('click', e => {
    const button = e.target.closest('[data-chart-tab]');
    if (!button) return;
    const card = tabGroup.closest('.chart-card') || document;
    const key = button.dataset.chartTab;
    tabGroup.querySelectorAll('[data-chart-tab]').forEach(tab => tab.classList.toggle('active', tab === button));
    card.querySelectorAll('[data-chart-panel]').forEach(panel => {
      panel.style.display = panel.dataset.chartPanel === key ? '' : 'none';
    });
  });
});

const savedTheme = localStorage.getItem('eduquest-theme');
if (savedTheme === 'dark') document.body.classList.add('dark');
document.querySelector('[data-dark-toggle]')?.addEventListener('click', () => {
  document.body.classList.toggle('dark');
  localStorage.setItem('eduquest-theme', document.body.classList.contains('dark') ? 'dark' : 'light');
});

const savedSidebar = localStorage.getItem('eduquest-sidebar');
if (savedSidebar === 'collapsed') {
  document.body.classList.add('sidebar-collapsed');
  document.documentElement.classList.add('sidebar-collapsed');
}
function toggleSidebar() {
  if (window.matchMedia('(max-width: 900px)').matches) {
    document.body.classList.toggle('sidebar-open');
    return;
  }
  document.body.classList.toggle('sidebar-collapsed');
  document.documentElement.classList.toggle('sidebar-collapsed', document.body.classList.contains('sidebar-collapsed'));
  localStorage.setItem('eduquest-sidebar', document.body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
}
document.addEventListener('click', e => {
  if (e.target.closest('[data-sidebar-toggle],.sidebar-toggle')) {
    e.preventDefault();
    toggleSidebar();
  }
});

document.addEventListener('click', e => {
  const toggle = e.target.closest('[data-accordion-toggle]');
  if (!toggle) return;
  toggle.closest('.subject-group,.lesson-group')?.classList.toggle('open');
});

document.querySelectorAll('.exam-choice input[type="radio"]').forEach(input => {
  input.addEventListener('change', () => {
    document.querySelectorAll('.exam-choice').forEach(item => item.classList.remove('selected'));
    input.closest('.exam-choice')?.classList.add('selected');
  });
});

document.addEventListener('click', e => {
  const toggle = e.target.closest('[data-password-toggle]');
  if (!toggle) return;
  const field = toggle.closest('.input-icon,.password-field');
  const input = field?.querySelector('input');
  const icon = toggle.querySelector('.material-symbols-outlined');
  if (!input) return;
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  if (icon) icon.textContent = show ? 'visibility_off' : 'visibility';
});

document.querySelectorAll('.sidebar .nav').forEach(link => {
  link.addEventListener('click', () => {
    if (window.matchMedia('(max-width: 900px)').matches) document.body.classList.remove('sidebar-open');
  });
});

document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', () => {
    const btn = form.querySelector('button.btn.primary');
    if (btn && !btn.dataset.confirm) {
      btn.dataset.originalText = btn.innerHTML;
      btn.innerHTML = '<span class="material-symbols-outlined">progress_activity</span> Đang xử lý...';
    }
  });
});

document.querySelector('[data-save-draft]')?.addEventListener('click', () => {
  alert('Đã lưu tạm bài làm trên trình duyệt.');
});

const timer = document.querySelector('.timer[data-minutes]');
if (timer) {
  let remain = parseInt(timer.dataset.minutes, 10) * 60;
  setInterval(() => {
    if (remain <= 0) return;
    remain--;
    const m = String(Math.floor(remain / 60)).padStart(2, '0');
    const s = String(remain % 60).padStart(2, '0');
    timer.textContent = `${m}:${s}`;
    if (remain < 300) timer.classList.add('danger');
  }, 1000);
}

document.querySelectorAll('[data-answer-input]').forEach(input => {
  input.addEventListener('change', () => {
    document.querySelector(`[data-qnav="${input.dataset.answerInput}"]`)?.classList.add('answered');
  });
});

const notificationShell = document.querySelector('[data-notification-shell]');
const notificationToggle = document.querySelector('[data-notification-toggle]');
const notificationModal = document.querySelector('[data-notification-modal]');

function closeNotifications() {
  notificationShell?.classList.remove('open');
}

notificationToggle?.addEventListener('click', e => {
  e.preventDefault();
  notificationShell?.classList.toggle('open');
});

document.addEventListener('click', e => {
  if (notificationShell && !e.target.closest('[data-notification-shell]')) closeNotifications();
  if (e.target.closest('[data-notification-close]')) notificationModal?.classList.remove('open');
});

document.querySelectorAll('[data-notification-item]').forEach(item => {
  item.addEventListener('click', () => {
    closeNotifications();
    if (!notificationModal) return;
    notificationModal.querySelector('[data-notification-modal-title]').textContent = item.dataset.title || '';
    notificationModal.querySelector('[data-notification-modal-meta]').textContent = `${item.dataset.sender || ''} · ${item.dataset.created || ''}`;
    notificationModal.querySelector('[data-notification-modal-content]').textContent = item.dataset.content || '';
    notificationModal.classList.add('open');
    item.classList.remove('unread');

    const body = new FormData();
    body.append('id', item.dataset.id || '');
    fetch('notification-read.php', { method: 'POST', body })
      .then(res => res.json())
      .then(data => {
        const badge = document.querySelector('[data-notification-badge]');
        if (!badge) return;
        if ((data.unread || 0) > 0) badge.textContent = data.unread > 99 ? '99+' : data.unread;
        else badge.remove();
      })
      .catch(() => {});
  });
});
