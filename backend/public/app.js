const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
const dayNames = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
const timeSlots = ['morning', 'afternoon', 'evening'];
const timeSlotNames = { 'morning': 'VM', 'afternoon': 'NM', 'evening': 'AB' };
const timeSlotNamesFull = { 'morning': 'Vormittag', 'afternoon': 'Nachmittag', 'evening': 'Abend' };

let currentMonth = new Date();
let selectedSlots = {};
let groupData = {};
let currentUser = '';
let currentCode = '';

const elements = {
    tabs: document.querySelectorAll('.tab'),
    tabContents: document.querySelectorAll('.tab-content'),
    calendar: document.getElementById('calendar'),
    monthDisplay: document.getElementById('monthDisplay'),
    messageArea: document.getElementById('messageArea'),
    matchesList: document.getElementById('matchesList'),
    partialMatchesList: document.getElementById('partialMatchesList'),
    participantsList: document.getElementById('participantsList'),
    nameInput: document.getElementById('name'),
    codeInput: document.getElementById('code'),
    passwordInput: document.getElementById('password'),
    prevMonthBtn: document.getElementById('prevMonth'),
    nextMonthBtn: document.getElementById('nextMonth'),
    saveButton: document.getElementById('saveAvailability')
};

function formatDate(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function parseDate(dateStr) {
    const [year, month, day] = dateStr.split('-').map(Number);
    return new Date(year, month - 1, day);
}

function formatDateGerman(dateStr) {
    const date = parseDate(dateStr);
    const dayName = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'][date.getDay()];
    return `${dayName}, ${date.getDate()}. ${monthNames[date.getMonth()]} ${date.getFullYear()}`;
}

function showMessage(text, type = 'success') {
    if (!text) {
        elements.messageArea.innerHTML = '';
        return;
    }

    const className = type === 'error' ? 'error-message' : 'success-message';
    elements.messageArea.innerHTML = `<div class="${className}">${text}</div>`;
    if (type !== 'error') {
        setTimeout(() => {
            elements.messageArea.innerHTML = '';
        }, 3000);
    }
}

function switchTab(tabName, target) {
    elements.tabs.forEach(tab => tab.classList.remove('active'));
    elements.tabContents.forEach(content => content.classList.remove('active'));

    target.classList.add('active');
    document.getElementById(`${tabName}-tab`).classList.add('active');

    if (tabName === 'matches') renderMatches();
    if (tabName === 'participants') renderParticipants();
}

function renderCalendar() {
    elements.calendar.innerHTML = '';
    elements.monthDisplay.textContent = `${monthNames[currentMonth.getMonth()]} ${currentMonth.getFullYear()}`;

    dayNames.forEach(day => {
        const label = document.createElement('div');
        label.className = 'day-label';
        label.textContent = day;
        elements.calendar.appendChild(label);
    });

    const firstDay = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1);
    const lastDay = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 0);
    const startDay = firstDay.getDay();

    for (let i = 0; i < startDay; i += 1) {
        const empty = document.createElement('div');
        empty.className = 'day-cell empty';
        elements.calendar.appendChild(empty);
    }

    for (let day = 1; day <= lastDay.getDate(); day += 1) {
        const cell = document.createElement('div');
        cell.className = 'day-cell';

        const dateStr = formatDate(new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day));

        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = day;
        dayNumber.addEventListener('click', () => toggleWholeDay(dateStr));
        cell.appendChild(dayNumber);

        const timeSlotsDiv = document.createElement('div');
        timeSlotsDiv.className = 'time-slots';

        timeSlots.forEach(slot => {
            const slotDiv = document.createElement('div');
            slotDiv.className = 'time-slot';
            slotDiv.textContent = timeSlotNames[slot];

            if (selectedSlots[dateStr] && selectedSlots[dateStr].includes(slot)) {
                slotDiv.classList.add('selected');
            }

            slotDiv.addEventListener('click', event => {
                event.stopPropagation();
                toggleTimeSlot(dateStr, slot, slotDiv);
            });

            timeSlotsDiv.appendChild(slotDiv);
        });

        cell.appendChild(timeSlotsDiv);
        elements.calendar.appendChild(cell);
    }
}

function toggleTimeSlot(dateStr, slot, element) {
    if (!selectedSlots[dateStr]) {
        selectedSlots[dateStr] = [];
    }

    const index = selectedSlots[dateStr].indexOf(slot);
    if (index > -1) {
        selectedSlots[dateStr].splice(index, 1);
        element.classList.remove('selected');
        if (selectedSlots[dateStr].length === 0) {
            delete selectedSlots[dateStr];
        }
    } else {
        selectedSlots[dateStr].push(slot);
        element.classList.add('selected');
    }
}

function toggleWholeDay(dateStr) {
    const allSelected = selectedSlots[dateStr] && selectedSlots[dateStr].length === 3;

    if (allSelected) {
        delete selectedSlots[dateStr];
    } else {
        selectedSlots[dateStr] = [...timeSlots];
    }

    renderCalendar();
}

function changeMonth(delta) {
    currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + delta, 1);
    renderCalendar();
}

async function apiRequest(action, payload = null, method = 'GET') {
    if (method === 'GET') {
        const params = new URLSearchParams(payload);
        const response = await fetch(`api.php?action=${action}&${params.toString()}`);
        return response.json();
    }

    const response = await fetch(`api.php?action=${action}`,
        {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
    return response.json();
}

async function saveAvailability() {
    const name = elements.nameInput.value.trim();
    const code = elements.codeInput.value.trim();
    const password = elements.passwordInput.value.trim();

    if (!name || !code || !password) {
        showMessage('Bitte fülle Name, Gruppen-Code und Passwort aus!', 'error');
        return;
    }

    if (Object.keys(selectedSlots).length === 0) {
        showMessage('Bitte wähle mindestens ein Zeitfenster aus!', 'error');
        return;
    }

    try {
        const response = await apiRequest('save', {
            name,
            code,
            password,
            slots: selectedSlots
        }, 'POST');

        if (!response.success) {
            showMessage(response.message || 'Fehler beim Speichern.', 'error');
            return;
        }

        currentUser = name;
        currentCode = code;
        groupData[code] = response.data || {};

        showMessage('✓ Deine Verfügbarkeit wurde gespeichert!');
        renderParticipants();
        renderMatches();
    } catch (error) {
        showMessage('Fehler beim Speichern. Bitte versuche es erneut.', 'error');
    }
}

async function loadGroupData() {
    const code = elements.codeInput.value.trim();
    const password = elements.passwordInput.value.trim();

    if (!code || !password) return;

    try {
        const response = await apiRequest('load', { code, password });

        if (!response.success) {
            showMessage(response.message || 'Fehler beim Laden der Gruppe.', 'error');
            groupData[code] = {};
            renderParticipants();
            renderMatches();
            return;
        }

        currentCode = code;
        groupData[code] = response.data || {};
        hydrateCurrentUser();
        renderParticipants();
        renderMatches();
    } catch (error) {
        showMessage('Verbindungsproblem beim Laden der Gruppe.', 'error');
        groupData[code] = {};
        renderParticipants();
        renderMatches();
    }
}

function hydrateCurrentUser() {
    const name = elements.nameInput.value.trim();
    if (!name || !currentCode) return;

    if (groupData[currentCode] && groupData[currentCode][name]) {
        selectedSlots = groupData[currentCode][name];
    } else {
        selectedSlots = {};
    }

    renderCalendar();
}

function renderMatches() {
    if (!currentCode || !groupData[currentCode] || Object.keys(groupData[currentCode]).length < 2) {
        elements.matchesList.innerHTML = '<div class="no-data">Mindestens 2 Teilnehmer benötigt.</div>';
        elements.partialMatchesList.innerHTML = '<div class="no-data">Mindestens 2 Teilnehmer benötigt.</div>';
        return;
    }

    const participants = groupData[currentCode];
    const participantNames = Object.keys(participants);
    const allDateSlots = {};

    participantNames.forEach(name => {
        const userSlots = participants[name];
        Object.keys(userSlots).forEach(date => {
            userSlots[date].forEach(slot => {
                const key = `${date}_${slot}`;
                if (!allDateSlots[key]) {
                    allDateSlots[key] = { date, slot, participants: [] };
                }
                allDateSlots[key].participants.push(name);
            });
        });
    });

    const perfectMatches = [];
    const partialMatches = [];

    Object.values(allDateSlots).forEach(item => {
        if (item.participants.length === participantNames.length) {
            perfectMatches.push(item);
        } else if (item.participants.length > 1) {
            item.missing = participantNames.filter(name => !item.participants.includes(name));
            partialMatches.push(item);
        }
    });

    const groupedPerfect = {};
    perfectMatches.forEach(item => {
        if (!groupedPerfect[item.date]) groupedPerfect[item.date] = [];
        groupedPerfect[item.date].push(item.slot);
    });

    const groupedPartial = {};
    partialMatches.forEach(item => {
        const key = `${item.date}_${item.participants.join('_')}`;
        if (!groupedPartial[key]) {
            groupedPartial[key] = {
                date: item.date,
                slots: [],
                participants: item.participants,
                missing: item.missing
            };
        }
        groupedPartial[key].slots.push(item.slot);
    });

    const sortedPerfect = Object.keys(groupedPerfect).sort();
    const sortedPartial = Object.values(groupedPartial).sort((a, b) => a.date.localeCompare(b.date));

    if (sortedPerfect.length === 0) {
        elements.matchesList.innerHTML = '<div class="no-data">Noch keine gemeinsamen Zeiten gefunden. 😔</div>';
    } else {
        let html = '';
        sortedPerfect.forEach(date => {
            const slots = groupedPerfect[date];
            html += `
                <div class="match-card">
                    <div class="match-date">${formatDateGerman(date)}</div>
                    <div class="match-time-slots">
                        ${slots.map(slot => `<span class="match-time-badge">${timeSlotNamesFull[slot]}</span>`).join('')}
                    </div>
                    <div class="match-participants" style="margin-top: 10px;">✓ Alle verfügbar</div>
                </div>
            `;
        });
        elements.matchesList.innerHTML = html;
    }

    if (sortedPartial.length === 0) {
        elements.partialMatchesList.innerHTML = '<div class="no-data">Keine teilweisen Übereinstimmungen.</div>';
    } else {
        let html = '';
        sortedPartial.forEach(item => {
            html += `
                <div class="participant-card">
                    <div class="participant-name">${formatDateGerman(item.date)}</div>
                    <div style="margin-top: 8px;">
                        ${item.slots.map(slot => `<span class="time-badge" style="background: #fbbf24;">${timeSlotNamesFull[slot]}</span>`).join('')}
                    </div>
                    <div class="participant-dates" style="margin-top: 8px;">✓ Verfügbar: ${item.participants.join(', ')}</div>
                    <div class="participant-dates" style="margin-top: 5px; color: #dc2626;">✗ Nicht verfügbar: ${item.missing.join(', ')}</div>
                </div>
            `;
        });
        elements.partialMatchesList.innerHTML = html;
    }
}

function renderParticipants() {
    if (!currentCode || !groupData[currentCode] || Object.keys(groupData[currentCode]).length === 0) {
        elements.participantsList.innerHTML = '<div class="no-data">Noch keine Teilnehmer.</div>';
        return;
    }

    const participants = groupData[currentCode];
    let html = '';

    Object.entries(participants).forEach(([name, slots]) => {
        const entries = [];
        Object.keys(slots).sort().forEach(date => {
            const times = slots[date].map(slot => timeSlotNames[slot]).join(', ');
            const d = parseDate(date);
            entries.push(`${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}.${d.getFullYear()} (${times})`);
        });

        html += `
            <div class="participant-card">
                <div class="participant-name">${name}</div>
                <div class="participant-dates">Verfügbar: ${entries.join(' • ')}</div>
            </div>
        `;
    });

    elements.participantsList.innerHTML = html;
}

elements.tabs.forEach(tab => {
    tab.addEventListener('click', event => {
        const tabName = event.currentTarget.dataset.tab;
        switchTab(tabName, event.currentTarget);
    });
});

elements.prevMonthBtn.addEventListener('click', () => changeMonth(-1));
elements.nextMonthBtn.addEventListener('click', () => changeMonth(1));
elements.saveButton.addEventListener('click', saveAvailability);

elements.codeInput.addEventListener('blur', loadGroupData);
elements.passwordInput.addEventListener('blur', loadGroupData);
elements.nameInput.addEventListener('blur', hydrateCurrentUser);

['codeInput', 'passwordInput', 'nameInput'].forEach(field => {
    elements[field].addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            loadGroupData();
            hydrateCurrentUser();
        }
    });
});

renderCalendar();
renderParticipants();
renderMatches();
