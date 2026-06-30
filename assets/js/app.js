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

function resetPreviewQuestion(card) {
  card.querySelectorAll('[data-option-list]').forEach(list => {
    list.querySelectorAll('[data-option-label]').forEach(row => {
      if (!['A', 'B', 'C', 'D'].includes((row.dataset.optionLabel || '').toUpperCase())) row.remove();
    });
  });
  card.querySelectorAll('textarea').forEach(field => { field.value = ''; });
  card.querySelectorAll('input').forEach(field => {
    if (field.type === 'checkbox') field.checked = false;
    else if (field.type === 'hidden') field.value = '';
    else field.value = '';
  });
  card.querySelectorAll('select').forEach(select => {
    if (select.name.includes('[type]')) select.value = 'mixed';
    else if (select.name.includes('[difficulty]')) select.value = 'unknown';
    else if (select.name.includes('[answer]')) {
      Array.from(select.options).forEach(option => {
        if (!['A', 'B', 'C', 'D'].includes(option.value)) option.remove();
      });
      select.value = select.name.includes('[tf_items]') ? 'true' : 'A';
    }
  });
  card.querySelectorAll('.question-image').forEach(image => image.remove());
  card.querySelectorAll('[data-question-type]').forEach(refreshQuestionEditor);
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
    const last = cards[cards.length - 1];
    if (!form || !last) return;
    const clone = last.cloneNode(true);
    resetPreviewQuestion(clone);
    last.after(clone);
    renumberPreviewQuestions(form);
    clone.querySelector('textarea, input:not([type="hidden"]), select')?.focus();
  });
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
