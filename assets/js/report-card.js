// Reports page enhancements
(function () {
  const form = document.querySelector('#reportsFilterForm');
  if (!form) return;

  const studentCode = form.querySelector('input[name="student_code"]');
  const studentSelect = form.querySelector('select[name="student_id"]');
  const courseSelect = form.querySelector('select[name="course_id"]');

  // Submit on Enter in student_code
  studentCode && studentCode.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      form.requestSubmit();
    }
  });

  // Keep student_code and select in sync
  if (studentCode && studentSelect) {
    studentSelect.addEventListener('change', () => {
      const opt = studentSelect.options[studentSelect.selectedIndex];
      if (!opt || !opt.text) return;
      const match = opt.text.match(/\(([^)]+)\)$/); // extract (STU123)
      if (match) studentCode.value = match[1];
    });
  }

  // Optional auto-submit on course change
  courseSelect && courseSelect.addEventListener('change', () => {
    form.requestSubmit();
  });

  // Smooth scroll to details if a student is selected
  const details = document.querySelector('#studentDetails');
  const hasSelected = studentSelect && studentSelect.value && studentSelect.value !== '';
  if (details && hasSelected) {
    setTimeout(() => { details.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 100);
  }

  // Print handler for any .js-print buttons
  document.querySelectorAll('.js-print').forEach(btn => {
    btn.addEventListener('click', () => window.print());
  });
})();
