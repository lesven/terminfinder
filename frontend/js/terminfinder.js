/**
 * Terminfinder JavaScript Frontend
 * Handles UI interactions and backend communication
 */

// Global state
let currentMonth = new Date();
let selectedSlots = {};
let groupData = {};
let currentUser = '';
let currentCode = '';
let isAuthenticated = false;
let isLoadingUserData = false; // Flag to prevent auto-save during data loading

// Auto-save timer
let autoSaveTimer = null;
const AUTO_SAVE_DELAY = 1000; // ms

// Constants
const API_BASE = '/api';
const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
const dayNames = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
const timeSlots = ['morning', 'afternoon', 'evening'];
const timeSlotNames = { 'morning': 'VM', 'afternoon': 'NM', 'evening': 'AB' };
const timeSlotNamesFull = { 
    'morning': 'Vormittag', 
    'afternoon': 'Nachmittag', 
    'evening': 'Abend'
};

// Function to get display name for time slot
function getTimeSlotDisplay(slot) {
    // If it's a predefined slot, use the mapping
    if (timeSlotNamesFull[slot]) {
        return timeSlotNamesFull[slot];
    }
    // If it's a specific time (HH:MM format), return it as-is
    if (slot.match(/^\d{1,2}:\d{2}$/)) {
        return slot + ' Uhr';
    }
    // Fallback
    return slot;
}

/**
 * Utility Functions
 */
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

function showMessage(message, type = 'success') {
    const container = document.getElementById('messageContainer');
    const messageClass = type === 'success' ? 'success-message' : 'error-message';
    container.innerHTML = `<div class="${messageClass}">${message}</div>`;
    setTimeout(() => container.innerHTML = '', 4000);
}

function showLoading(show = true) {
    const saveBtn = document.getElementById('saveBtn');
    if (show) {
        saveBtn.classList.add('loading');
        saveBtn.innerHTML = '<div class="spinner"></div>';
    } else {
        saveBtn.classList.remove('loading');
        saveBtn.innerHTML = 'Verfügbarkeit speichern';
    }
}

/**
 * API Functions
 */
async function apiCall(endpoint, data) {
    try {
        const response = await fetch(`${API_BASE}/${endpoint}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.error || 'API request failed');
        }
        
        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

async function authenticateGroup(code, password) {
    return await apiCall('groups.php', {
        action: 'authenticate',
        code: code,
        password: password
    });
}

function normalizeAvailabilitiesForApi(availabilities) {
    console.log('normalizeAvailabilitiesForApi input:', availabilities);

    // Handle arrays first
    if (Array.isArray(availabilities)) {
        // Detect arrays that are used like objects with string keys (these are not serialized by JSON.stringify and become [])
        const keys = Object.keys(availabilities);
        const hasNonNumericKey = keys.some(k => isNaN(Number(k)));
        if (hasNonNumericKey && keys.length > 0) {
            const obj = {};
            keys.forEach(k => { obj[k] = availabilities[k]; });
            console.warn('normalizeAvailabilitiesForApi converted array-with-string-keys to object:', obj);
            return obj;
        }

        // Otherwise it's a proper array-of-objects (format 2)
        console.log('returning array as-is:', availabilities);
        return availabilities;
    }

    // If it's an object mapping date => [slots], return it as-is (backend supports associative format)
    if (availabilities && typeof availabilities === 'object') {
        console.log('returning object mapping as-is:', availabilities);
        return availabilities;
    }

    console.log('normalizeAvailabilitiesForApi result: []');
    return [];
}

async function saveUserAvailability(groupCode, userName, availabilities) {
    const payloadAvail = normalizeAvailabilitiesForApi(availabilities);
    console.debug('Saving availability payload:', { groupCode, userName, availabilities: payloadAvail });

    return await apiCall('availability.php', {
        action: 'save',
        groupCode: groupCode,
        userName: userName,
        availabilities: payloadAvail
    });
}

async function loadGroupData(code) {
    return await apiCall('groups.php', {
        action: 'getGroupData',
        code: code
    });
}

// Get a specific user's availability from the server
async function getUserAvailability(groupCode, userName) {
    return await apiCall('availability.php', {
        action: 'getUserAvailability',
        groupCode: groupCode,
        userName: userName
    });
}

// Debounced auto-save of current selectedSlots for the logged-in user
function scheduleAutoSave() {
    console.log('scheduleAutoSave called:', { 
        isAuthenticated, 
        currentCode, 
        currentUser, 
        selectedSlots, 
        slotsCount: Object.keys(selectedSlots).length,
        isLoadingUserData
    });
    
    if (!isAuthenticated || !currentCode || !currentUser) return;
    
    // Don't auto-save if we're currently loading user data
    if (isLoadingUserData) return;
    
    // Don't auto-save if no slots are selected
    if (Object.keys(selectedSlots).length === 0) return;
    
    if (autoSaveTimer) clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(async () => {
        try {
            const result = await saveUserAvailability(currentCode, currentUser, selectedSlots);
            if (result && result.success) {
                showMessage('Automatisch gespeichert', 'success');
                await refreshGroupData();
            } else {
                showMessage(result.message || 'Automatisches Speichern fehlgeschlagen', 'error');
            }
        } catch (e) {
            showMessage('Automatisches Speichern fehlgeschlagen: ' + e.message, 'error');
        }
    }, AUTO_SAVE_DELAY);
}

// When user leaves the name field, load their saved availability into the calendar
async function onNameBlur() {
    const name = document.getElementById('name').value.trim();
    if (!name) {
        currentUser = '';
        selectedSlots = {};
        renderCalendar();
        return;
    }
    if (!isAuthenticated || !currentCode) {
        // Not logged in yet — nothing to load
        return;
    }

    currentUser = name;
    isLoadingUserData = true; // Set flag to prevent auto-save during loading

    try {
        const res = await getUserAvailability(currentCode, currentUser);
        if (res.success) {
            selectedSlots = res.data || {};
            renderCalendar();
            showMessage('Deine gespeicherten Termine wurden geladen', 'success');
        } else {
            selectedSlots = {};
            renderCalendar();
            if (res.message) showMessage(res.message, 'error');
        }
    } catch (e) {
        showMessage('Fehler beim Laden deiner Termine: ' + e.message, 'error');
    } finally {
        isLoadingUserData = false; // Clear flag after loading is complete
    }
} 

/**
 * Authentication Functions
 */
async function handleLogin() {
    const code = document.getElementById('loginCode').value.trim();
    const password = document.getElementById('loginPassword').value.trim();
    
    if (!code || !password) {
        showLoginMessage('Bitte gib sowohl Gruppen-Code als auch Passwort ein.', 'error');
        return;
    }

    // Show loading state
    const loginBtn = document.getElementById('loginBtn');
    loginBtn.innerHTML = '<div class="spinner"></div>';
    loginBtn.disabled = true;

    try {
        const result = await authenticateGroup(code, password);
        
        if (result.success) {
            isAuthenticated = true;
            currentCode = code;

            // Persist credentials for auto-login
            try {
                localStorage.setItem('tf_groupCode', code);
                localStorage.setItem('tf_groupPassword', password);
            } catch (e) {
                console.warn('Could not persist credentials:', e);
            }
            
            // Hide login screen and show main app
            document.getElementById('loginStatus').style.display = 'none';
            document.getElementById('mainApp').style.display = 'block';
            
            // Update the group display
            document.getElementById('currentGroupDisplay').textContent = code;
            
            // Load group data and render content
            await refreshGroupData();
            renderCalendar();
            
            updateLogoutButtonVisibility(true);
            showMessage('Erfolgreich angemeldet!', 'success');
        } else {
            showLoginMessage(result.message || 'Anmeldung fehlgeschlagen', 'error');
        }
    } catch (error) {
        showLoginMessage('Fehler bei der Anmeldung: ' + error.message, 'error');
    } finally {
        // Reset button
        loginBtn.innerHTML = 'Anmelden';
        loginBtn.disabled = false;
    }
}

function showLoginMessage(message, type = 'success') {
    const container = document.getElementById('loginMessageContainer');
    const messageClass = type === 'success' ? 'success-message' : 'error-message';
    container.innerHTML = `<div class="${messageClass}">${message}</div>`;
    setTimeout(() => container.innerHTML = '', 4000);
}

async function checkAuthentication() {
    // 1) Try token from URL fragment (safer than query because it's not sent to server on navigation)
    const hash = window.location.hash || '';
    const match = hash.match(/t=([a-f0-9]+)/i);
    if (match) {
        const token = match[1];
        const ok = await authenticateWithTokenFrontend(token);
        if (ok) return;
    }

    // 2) Try persisted share token
    const storedShareToken = localStorage.getItem('tf_share_token');
    if (storedShareToken) {
        const ok = await authenticateWithTokenFrontend(storedShareToken);
        if (ok) return;
        // invalid stored token -> remove
        localStorage.removeItem('tf_share_token');
    }

    // 3) Fallback to stored credentials (code + password)
    const storedCode = localStorage.getItem('tf_groupCode');
    const storedPassword = localStorage.getItem('tf_groupPassword');
    if (storedCode && storedPassword) {
        try {
            const result = await authenticateGroup(storedCode, storedPassword);
            if (result.success) {
                isAuthenticated = true;
                currentCode = storedCode;
                // Hide login and show app
                document.getElementById('loginStatus').style.display = 'none';
                document.getElementById('mainApp').style.display = 'block';
                document.getElementById('currentGroupDisplay').textContent = currentCode;
                await refreshGroupData();
                renderCalendar();
                updateLogoutButtonVisibility(true);
                showMessage('Automatisch angemeldet!', 'success');
                return;
            }
        } catch (e) {
            console.error('Auto-login failed:', e);
        }
        // If auto-login failed, clear stored credentials
        localStorage.removeItem('tf_groupCode');
        localStorage.removeItem('tf_groupPassword');
    }

    updateLogoutButtonVisibility(false);
}

// Authenticate using a share token (frontend)
async function authenticateWithTokenFrontend(token) {
    try {
        const res = await apiCall('groups.php', { action: 'authenticateWithToken', token });
        if (res && res.success) {
            isAuthenticated = true;
            currentCode = res.groupCode;
            // Persist token for continued access
            try { localStorage.setItem('tf_share_token', token); } catch (e) { console.warn('Could not persist share token:', e); }

            // Hide login and show app
            document.getElementById('loginStatus').style.display = 'none';
            document.getElementById('mainApp').style.display = 'block';
            document.getElementById('currentGroupDisplay').textContent = currentCode;

            await refreshGroupData();
            renderCalendar();
            updateLogoutButtonVisibility(true);
            showMessage('Mit Deeplink angemeldet!', 'success');

            // Remove token from URL fragment to avoid accidental sharing
            history.replaceState(null, '', window.location.pathname + window.location.search);
            return true;
        } else {
            return false;
        }
    } catch (e) {
        console.error('Token auth failed:', e);
        return false;
    }
}

// Create a share link for the current group
async function createShareLink() {
    if (!isAuthenticated || !currentCode) {
        showMessage('Du musst zuerst angemeldet sein, um einen Link zu erstellen', 'error');
        return;
    }

    // Ask for TTL in days (default 7)
    let ttl = prompt('Gültigkeit des Links in Tagen (leer = 7):', '7');
    if (ttl === null) return; // cancelled
    ttl = ttl.trim() === '' ? 7 : Math.max(1, parseInt(ttl, 10) || 7);

    // Use stored password if available, otherwise ask for it
    let password = localStorage.getItem('tf_groupPassword');
    if (!password) {
        password = prompt('Bitte gib das Gruppenpasswort zur Bestätigung ein:');
        if (password === null) return;
    }

    try {
        const res = await apiCall('groups.php', {
            action: 'createShareLink',
            code: currentCode,
            password,
            ttlDays: ttl
        });

        if (res && res.success) {
            const token = res.token;
            const link = window.location.origin + window.location.pathname + '#t=' + token;

            // show link and copy to clipboard
            try {
                await navigator.clipboard.writeText(link);
                showMessage('Link erstellt und in die Zwischenablage kopiert!', 'success');
            } catch (e) {
                // Fallback: show prompt with link
                prompt('Dein Share-Link (kopiere ihn):', link);
            }
        } else {
            showMessage(res.message || 'Konnte Link nicht erstellen', 'error');
        }
    } catch (e) {
        showMessage('Fehler beim Erstellen des Links: ' + e.message, 'error');
    }
}

function updateLogoutButtonVisibility(show) {
    const btn = document.getElementById('logoutBtn');
    const globalBtn = document.getElementById('globalLogoutBtn');
    const createBtn = document.getElementById('createShareBtn');
    if (btn) btn.style.display = show ? 'inline-block' : 'none';
    if (globalBtn) globalBtn.style.display = show ? 'inline-block' : 'none';
    if (createBtn) createBtn.style.display = show ? 'inline-block' : 'none';
}

function handleLogout() {
    // Clear stored credentials and reset app state
    localStorage.removeItem('tf_groupCode');
    localStorage.removeItem('tf_groupPassword');
    localStorage.removeItem('tf_share_token');

    isAuthenticated = false;
    currentCode = '';
    currentUser = '';
    groupData = {};

    // clear pending autosave
    if (autoSaveTimer) { clearTimeout(autoSaveTimer); autoSaveTimer = null; }

    document.getElementById('loginStatus').style.display = 'block';
    document.getElementById('mainApp').style.display = 'none';
    document.getElementById('loginCode').value = '';
    document.getElementById('loginPassword').value = '';
    document.getElementById('currentGroupDisplay').textContent = '';

    updateLogoutButtonVisibility(false);
    showMessage('Erfolgreich ausgeloggt', 'success');
}

async function refreshGroupData() {
    console.log('refreshGroupData called:', { isAuthenticated, currentCode });
    if (!isAuthenticated || !currentCode) return;
    
    try {
        const result = await loadGroupData(currentCode);
        console.log('Group data loaded:', result);
        if (result.success) {
            groupData[currentCode] = result.data;
            renderParticipants();
            renderMatches();
        }
    } catch (error) {
        console.error('Failed to load group data:', error);
    }
}

/**
 * Tab Navigation
 */
function switchTab(tabName) {
    console.log('Switch tab to:', tabName);
    
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Find the clicked tab and activate it
    const clickedTab = document.querySelector(`[onclick="switchTab('${tabName}')"]`);
    if (clickedTab) {
        clickedTab.classList.add('active');
    }
    
    const tabContent = document.getElementById(`${tabName}-tab`);
    if (tabContent) {
        tabContent.classList.add('active');
    }

    if (tabName === 'matches') {
        console.log('Loading matches tab, auth status:', isAuthenticated, 'code:', currentCode);
        // Ensure we have current data before showing matches
        if (isAuthenticated && currentCode) {
            refreshGroupData().then(() => renderMatches());
        } else {
            renderMatches();
        }
    } else if (tabName === 'participants') {
        if (isAuthenticated && currentCode) {
            refreshGroupData().then(() => renderParticipants());
        } else {
            renderParticipants();
        }
    }
}

/**
 * Debug Functions
 */


function testData() {
    console.log('Current state:', {
        isAuthenticated,
        currentCode,
        groupData
    });
    alert('Siehe Konsole für aktuelle Daten');
}

/**
 * Calendar Functions
 */
function renderCalendar() {
    const calendar = document.getElementById('calendar');
    const monthDisplay = document.getElementById('monthDisplay');
    
    monthDisplay.textContent = `${monthNames[currentMonth.getMonth()]} ${currentMonth.getFullYear()}`;
    calendar.innerHTML = '';
    
    // Day labels
    dayNames.forEach(day => {
        const label = document.createElement('div');
        label.className = 'day-label';
        label.textContent = day;
        calendar.appendChild(label);
    });

    const firstDay = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1);
    const lastDay = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 0);
    const startDay = firstDay.getDay();
    
    // Empty cells for previous month
    for (let i = 0; i < startDay; i++) {
        const empty = document.createElement('div');
        empty.className = 'day-cell empty';
        calendar.appendChild(empty);
    }

    // Days of current month
    for (let day = 1; day <= lastDay.getDate(); day++) {
        const cell = document.createElement('div');
        cell.className = 'day-cell';
        
        const dateStr = formatDate(new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day));
        
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = day;
        dayNumber.onclick = () => toggleWholeDay(dateStr);
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

            slotDiv.onclick = (e) => {
                e.stopPropagation();
                toggleTimeSlot(dateStr, slot, slotDiv);
            };

            timeSlotsDiv.appendChild(slotDiv);
        });

        cell.appendChild(timeSlotsDiv);
        calendar.appendChild(cell);
    }
}

function toggleTimeSlot(dateStr, slot, element) {
    console.log('toggleTimeSlot called:', { dateStr, slot, currentSlots: selectedSlots });
    
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
    
    console.log('selectedSlots after toggle:', selectedSlots);

    // Schedule an auto-save after changes
    scheduleAutoSave();
}

function toggleWholeDay(dateStr) {
    const allSelected = selectedSlots[dateStr] && selectedSlots[dateStr].length === 3;
    
    if (allSelected) {
        delete selectedSlots[dateStr];
    } else {
        selectedSlots[dateStr] = [...timeSlots];
    }
    
    renderCalendar();
    // After toggling a whole day, schedule an auto-save
    scheduleAutoSave();
}

function changeMonth(delta) {
    currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + delta, 1);
    renderCalendar();
}

/**
 * Save Availability
 */
async function saveAvailability() {
    const name = document.getElementById('name').value.trim();

    if (!name) {
        showMessage('Bitte gib deinen Namen ein!', 'error');
        return;
    }

    if (!isAuthenticated || !currentCode) {
        showMessage('Du musst dich zuerst anmelden!', 'error');
        return;
    }

    if (Object.keys(selectedSlots).length === 0) {
        showMessage('Bitte wähle mindestens ein Zeitfenster aus!', 'error');
        return;
    }

    currentUser = name;

    try {
        showLoading(true);

        // Save availability
        const result = await saveUserAvailability(currentCode, currentUser, selectedSlots);
        
        if (result.success) {
            showMessage('✓ Deine Verfügbarkeit wurde gespeichert!');
            await refreshGroupData();
        } else {
            showMessage(result.message, 'error');
        }

    } catch (error) {
        showMessage('Fehler beim Speichern: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
}

/**
 * Render Functions
 */
function renderMatches() {
    console.log('renderMatches called. Current state:', {
        currentCode,
        isAuthenticated,
        hasGroupData: !!groupData[currentCode],
        participantCount: groupData[currentCode] ? Object.keys(groupData[currentCode]).length : 0,
        groupData: groupData[currentCode]
    });
    
    const matchesList = document.getElementById('matchesList');
    const partialMatchesList = document.getElementById('partialMatchesList');
    
    if (!currentCode || !groupData[currentCode] || Object.keys(groupData[currentCode]).length < 2) {
        console.log('Not enough participants for matches');
        matchesList.innerHTML = '<div class="no-data">Mindestens 2 Teilnehmer benötigt für gemeinsame Termine.</div>';
        partialMatchesList.innerHTML = '<div class="no-data">Mindestens 2 Teilnehmer benötigt für teilweise Übereinstimmungen.</div>';
        return;
    }

    const participants = groupData[currentCode];
    const participantNames = Object.keys(participants);
    // Sort participant names alphabetically (German locale) for display
    const participantNamesSorted = participantNames.slice().sort((a, b) => a.localeCompare(b, 'de', { sensitivity: 'base' }));
    const allDateSlots = {};
    
    // Collect all date/slot combinations
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
        // Sort participants for consistent display and grouping
        item.participants.sort((a, b) => a.localeCompare(b, 'de', { sensitivity: 'base' }));

        if (item.participants.length === participantNames.length) {
            perfectMatches.push(item);
        } else if (item.participants.length > 1) {
            item.missing = participantNamesSorted.filter(n => !item.participants.includes(n));
            partialMatches.push(item);
        }
    });

    // Group by date
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

    // Render perfect matches
    const sortedPerfect = Object.keys(groupedPerfect).sort();
    if (sortedPerfect.length === 0) {
        matchesList.innerHTML = '<div class="no-data">Noch keine gemeinsamen Zeiten gefunden. 😔</div>';
    } else {
        let html = '';
        sortedPerfect.forEach(date => {
            const slots = groupedPerfect[date];
            html += `
                <div class="match-card">
                    <div class="match-date">${formatDateGerman(date)}</div>
                    <div class="match-time-slots">
                        ${slots.map(s => `<span class="match-time-badge">${getTimeSlotDisplay(s)}</span>`).join('')}
                    </div>
                    <div style="margin-top: 10px; opacity: 0.9;">
                        <div>✓ Alle ${participantNamesSorted.length} Teilnehmer verfügbar</div>
                        <div style="margin-top:6px; font-size:0.95em;">${participantNamesSorted.join(', ')}</div>
                    </div>
                </div>
            `;
        });
        matchesList.innerHTML = html;
    }

    // Render partial matches
    const sortedPartial = Object.values(groupedPartial).sort((a, b) => a.date.localeCompare(b.date));
    if (sortedPartial.length === 0) {
        partialMatchesList.innerHTML = '<div class="no-data">Keine teilweisen Übereinstimmungen gefunden.</div>';
    } else {
        let html = '';
        sortedPartial.forEach(item => {
            html += `
                <div class="partial-match-card">
                    <div class="match-date">${formatDateGerman(item.date)}</div>
                    <div class="match-time-slots">
                        ${item.slots.map(s => `<span class="match-time-badge">${getTimeSlotDisplay(s)}</span>`).join('')}
                    </div>
                    <div style="margin-top: 12px; font-size: 0.95em;">
                        <div>✓ Verfügbar: ${item.participants.join(', ')}</div>
                        <div style="margin-top: 4px; opacity: 0.8;">✗ Nicht verfügbar: ${item.missing.join(', ')}</div>
                    </div>
                </div>
            `;
        });
        partialMatchesList.innerHTML = html;
    }
}

function renderParticipants() {
    const participantsList = document.getElementById('participantsList');
    
    if (!currentCode || !groupData[currentCode] || Object.keys(groupData[currentCode]).length === 0) {
        participantsList.innerHTML = '<div class="no-data">Noch keine Teilnehmer in dieser Gruppe.</div>';
        return;
    }

    const participants = groupData[currentCode];
    let html = '';

    Object.entries(participants).forEach(([name, slots]) => {
        const entries = [];
        Object.keys(slots).sort().forEach(date => {
            const times = slots[date].map(s => timeSlotNames[s]).join(', ');
            const d = parseDate(date);
            entries.push(`${d.getDate()}.${(d.getMonth() + 1).toString().padStart(2, '0')}. (${times})`);
        });

        html += `
            <div class="participant-card">
                <div class="participant-name">${name}</div>
                <div class="participant-dates">Verfügbar: ${entries.join(' • ') || 'Keine Termine eingetragen'}</div>
            </div>
        `;
    });

    participantsList.innerHTML = html;
}

/**
 * Event Listeners
 */
document.addEventListener('DOMContentLoaded', function() {
    // Setup Enter key listeners for login form
    document.getElementById('loginCode').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') handleLogin();
    });
    document.getElementById('loginPassword').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') handleLogin();
    });

    // Load user's saved selection when leaving the name field
    const nameInput = document.getElementById('name');
    if (nameInput) nameInput.addEventListener('blur', onNameBlur);
    
    // Attempt automatic authentication if credentials are stored
    checkAuthentication();

    console.log('App initialized - waiting for login');
});

/**
 * Make functions globally available
 */
window.switchTab = switchTab;
window.changeMonth = changeMonth;
window.saveAvailability = saveAvailability;
window.handleLogin = handleLogin;
window.createShareLink = createShareLink;
window.authenticateWithTokenFrontend = authenticateWithTokenFrontend;