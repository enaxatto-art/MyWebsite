// Modern Student Portal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips and interactive elements
    initTooltips();
    initFormValidation();
    initDataTables();
    initNotifications();
    initAIPanel();
    initCalendar();
    initNotificationsCenter();
    initHeatmap();
    initSmartDocs();
    initGamification();
});

function initTooltips() {
    // Add tooltip functionality
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = e.target.dataset.tooltip;
    tooltip.style.cssText = `
        position: absolute;
        background: #1e293b;
        color: white;
        padding: 0.5rem;
        border-radius: 4px;
        font-size: 0.875rem;
        z-index: 1000;
        pointer-events: none;
    `;
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
}

function hideTooltip() {
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            showFieldError(input, 'This field is required');
            isValid = false;
        } else {
            clearFieldError(input);
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    clearFieldError(field);
    const error = document.createElement('div');
    error.className = 'field-error';
    error.textContent = message;
    error.style.cssText = 'color: #dc2626; font-size: 0.875rem; margin-top: 0.25rem;';
    field.parentNode.appendChild(error);
    field.style.borderColor = '#dc2626';
}

function clearFieldError(field) {
    const error = field.parentNode.querySelector('.field-error');
    if (error) {
        error.remove();
    }
    field.style.borderColor = '';
}

function initDataTables() {
    const tables = document.querySelectorAll('.data-table');
    tables.forEach(table => {
        addSearchAndPagination(table);
    });
}

function addSearchAndPagination(table) {
    const container = table.parentNode;
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search...';
    searchInput.className = 'form-input';
    searchInput.style.marginBottom = '1rem';
    
    container.insertBefore(searchInput, table);
    
    searchInput.addEventListener('input', function() {
        filterTable(table, this.value);
    });
}

function filterTable(table, searchTerm) {
    const rows = table.querySelectorAll('tbody tr');
    const term = searchTerm.toLowerCase();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
}

function initNotifications() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
}

// ==== AI Assistant Panel (frontend demo) ====
function initAIPanel(){
    const panel = document.getElementById('aiPanel');
    if(!panel) return;
    const toggle = document.getElementById('aiToggle');
    const closeBtn = document.getElementById('aiClose');
    const form = document.getElementById('aiForm');
    const input = document.getElementById('aiInput');
    const messages = document.getElementById('aiMessages');

    const open = () => panel.classList.add('open');
    const close = () => panel.classList.remove('open');
    toggle.addEventListener('click', open);
    closeBtn.addEventListener('click', close);
    form.addEventListener('submit', function(e){
        e.preventDefault();
        const text = input.value.trim();
        if(!text) return;
        messages.appendChild(renderMsg(text, 'user'));
        input.value='';
        // Fake response for demo
        setTimeout(()=>{
            const reply = generateAIReply(text);
            messages.appendChild(renderMsg(reply, 'bot'));
            messages.scrollTop = messages.scrollHeight;
        }, 600);
    });

    function renderMsg(text, role){
        const div = document.createElement('div');
        div.className = `msg ${role}`;
        div.textContent = text;
        return div;
    }
    function generateAIReply(q){
        const map = [
            'Here is a quick tip: use the Students page to manage enrollments.',
            'You can add assessments from the Courses > Assessments section.',
            'Marks can be viewed on the Marks page. Use filters to refine results.',
            'To add a new course, go to Courses and click Add Course.',
        ];
        return map[q.length % map.length];
    }
}

// ==== Dynamic Calendar (no external libs) ====
function initCalendar(){
    const grid = document.getElementById('calendarGrid');
    if(!grid) return;
    const title = document.getElementById('calTitle');
    const prev = document.getElementById('calPrev');
    const next = document.getElementById('calNext');
    let current = new Date();

    function render(){
        grid.innerHTML='';
        const y = current.getFullYear();
        const m = current.getMonth();
        const first = new Date(y, m, 1);
        const startDay = first.getDay();
        const daysInMonth = new Date(y, m+1, 0).getDate();
        title.textContent = `${current.toLocaleString('default',{month:'long'})} ${y}`;

        const weekDays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        weekDays.forEach(d=>{
            const head = document.createElement('div');
            head.textContent = d;
            head.style.fontWeight='700';
            head.style.textAlign='center';
            grid.appendChild(head);
        });

        for(let i=0;i<startDay;i++){
            const empty = document.createElement('div');
            grid.appendChild(empty);
        }
        const today = new Date();
        for(let d=1; d<=daysInMonth; d++){
            const cell = document.createElement('div');
            cell.className='cell';
            cell.textContent = d;
            if(d===today.getDate() && m===today.getMonth() && y===today.getFullYear()){
                cell.classList.add('today');
            }
            grid.appendChild(cell);
        }
    }
    prev.addEventListener('click', ()=>{ current.setMonth(current.getMonth()-1); render(); });
    next.addEventListener('click', ()=>{ current.setMonth(current.getMonth()+1); render(); });
    render();
}

// ==== Notifications Center ====
function initNotificationsCenter(){
    const list = document.getElementById('notifList');
    if(!list) return;
    const badge = document.getElementById('notifBadge');
    const markAll = document.getElementById('markAllRead');
    const data = [
        {id:1, text:'3 new students enrolled', time:'2m', unread:true},
        {id:2, text:'Assignment results updated for CS101', time:'1h', unread:true},
        {id:3, text:'System maintenance tomorrow 3PM', time:'1d', unread:false},
    ];
    function render(){
        list.innerHTML='';
        let unread=0;
        data.forEach(n=>{
            if(n.unread) unread++;
            const item = document.createElement('div');
            item.className = `notif-item ${n.unread?'unread':''}`;
            item.innerHTML = `<i class="fas fa-bell"></i><div style="flex:1;">${n.text}<br><small style="color:#64748b">${n.time} ago</small></div><button class="btn btn-secondary btn-sm">Mark read</button>`;
            item.querySelector('button').addEventListener('click',()=>{ n.unread=false; render(); });
            list.appendChild(item);
        });
        badge.textContent = unread;
    }
    markAll.addEventListener('click', ()=>{ data.forEach(n=>n.unread=false); render(); });
    render();
}

// ==== Heatmap (demo data) ====
function initHeatmap(){
    const container = document.getElementById('heatmap');
    if(!container) return;
    container.innerHTML='';
    const days = 14*7; // 14 weeks
    for(let i=0;i<days;i++){
        const level = Math.floor(Math.random()*5); // 0-4
        const cell = document.createElement('div');
        cell.className='heat-cell';
        if(level>0) cell.setAttribute('data-level', String(level));
        cell.setAttribute('data-title', `${level} activities`);
        container.appendChild(cell);
    }
}

// ==== Smart Document Center (frontend demo) ====
function initSmartDocs(){
    const list = document.getElementById('docsList');
    if(!list) return;
    const uploadBtn = document.getElementById('uploadDocBtn');
    const docs = [
        {name:'Syllabus_CS101.pdf', size:'220 KB'},
        {name:'Exam_Timetable.xlsx', size:'48 KB'},
    ];
    function render(){
        list.innerHTML='';
        docs.forEach((d,idx)=>{
            const row = document.createElement('div');
            row.className='doc-item';
            row.innerHTML = `<span><i class="fas fa-file"></i> ${d.name}</span><span style="color:#64748b">${d.size}</span>`;
            list.appendChild(row);
        });
    }
    uploadBtn.addEventListener('click', (e)=>{
        e.preventDefault();
        alert('Upload flow placeholder. Hook to backend when ready.');
    });
    render();
}

// ==== Gamification (demo progression) ====
function initGamification(){
    const lvl = document.getElementById('levelNum');
    if(!lvl) return;
    const bar = document.getElementById('levelProgress');
    const xpText = document.getElementById('xpText');
    const streak = document.getElementById('streakDays');
    let xp = 200, target = 1000, level = 1, s = 3;
    function update(){
        const pct = Math.min(100, Math.round(xp/target*100));
        bar.style.width = pct + '%';
        xpText.textContent = `${xp} / ${target} XP`;
        lvl.textContent = String(level);
        streak.textContent = String(s);
    }
    update();
}

// Utility functions
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        max-width: 400px;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// AJAX helper
function ajaxRequest(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    return fetch(url, { ...defaultOptions, ...options })
        .then(response => response.json())
        .catch(error => {
            console.error('AJAX Error:', error);
            showNotification('An error occurred. Please try again.', 'danger');
        });
}
