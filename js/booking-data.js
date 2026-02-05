/**
 * KORTZEN - Booking Data (Connected to PHP API)
 * Fetches barbers and schedules from the PHP backend with localStorage fallback.
 */

const KORTZEN_DB_KEY = 'kortzen_db';

// Cached data
let BARBERS = [];
let SCHEDULES = {};
let NEXT_14_DAYS = [];

// Initialize dates immediately
function initDates(days = 14) {
    const dates = [];
    const today = new Date();

    for (let i = 0; i < days; i++) {
        const date = new Date(today);
        date.setDate(today.getDate() + i);
        dates.push({
            dateObj: date,
            dateString: date.toISOString().split('T')[0],
            dayName: date.toLocaleDateString('es-ES', { weekday: 'short' }),
            dayNumber: date.getDate(),
            dayOfWeek: date.getDay(),
            fullDate: date.toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' })
        });
    }
    return dates;
}

NEXT_14_DAYS = initDates();

// Load barbers from PHP API or localStorage fallback
async function loadBarbersFromAPI() {
    // Try PHP API first
    if (window.KortzenAPI) {
        try {
            const barbers = await KortzenAPI.getBarbers();
            if (barbers && barbers.length > 0) {
                console.log('✅ Barbers loaded from API:', barbers.length);
                return barbers.map(b => ({
                    id: b.id,
                    name: b.name,
                    role: b.role || 'Barbero',
                    image: b.image || `https://ui-avatars.com/api/?name=${encodeURIComponent(b.name)}&background=C0A062&color=121212&size=256`,
                    specialty: b.specialty || 'Corte Premium',
                    available: true,
                    workingDays: b.workingDays || [1, 2, 3, 4, 5, 6]
                }));
            }
        } catch (e) {
            console.warn('⚠️ API not available, using fallback:', e);
        }
    }

    // Fallback to localStorage
    return loadBarbersFromLocalStorage();
}

// Load barbers from localStorage (legacy)
function loadBarbersFromLocalStorage() {
    try {
        const dbRaw = localStorage.getItem(KORTZEN_DB_KEY);
        if (dbRaw) {
            const db = JSON.parse(dbRaw);
            if (db.users && db.users.length > 0) {
                return db.users
                    .filter(u => u.role === 'barber')
                    .map(u => ({
                        id: u.id,
                        name: u.name,
                        role: u.specialties?.join(' | ') || "Barbero",
                        image: `https://ui-avatars.com/api/?name=${encodeURIComponent(u.name)}&background=C0A062&color=121212&size=256&font-size=0.40&bold=true`,
                        specialty: u.specialties?.join(', ') || "Corte Premium",
                        available: true,
                        workingDays: u.workingDays || [1, 2, 3, 4, 5, 6]
                    }));
            }
        }
    } catch (e) {
        console.warn('⚠️ Error loading barbers from localStorage:', e);
    }

    // Ultimate fallback to static data
    console.log('📦 Using fallback static barbers');
    return getStaticBarbers();
}

function getStaticBarbers() {
    return [
        {
            id: 1,
            name: "Marco Rossi",
            role: "Maestro Barbero",
            image: "https://images.unsplash.com/photo-1583900985315-953be8115367?ixlib=rb-1.2.1&auto=format&fit=crop&w=634&q=80",
            specialty: "Corte Clásico & Afeitado",
            available: true,
            workingDays: [1, 2, 3, 4, 5, 6]
        },
        {
            id: 2,
            name: "David Chen",
            role: "Estilista Senior",
            image: "https://images.unsplash.com/photo-1618077360395-f3068be8e001?ixlib=rb-1.2.1&auto=format&fit=crop&w=634&q=80",
            specialty: "Fade & Diseños Modernos",
            available: true,
            workingDays: [1, 2, 3, 4, 5]
        },
        {
            id: 3,
            name: "Elena Vega",
            role: "Especialista en Barba",
            image: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-1.2.1&auto=format&fit=crop&w=634&q=80",
            specialty: "Tratamientos Faciales & Barba",
            available: true,
            workingDays: [2, 3, 4, 5, 6]
        }
    ];
}

// Load schedule from API or generate locally
async function loadScheduleFromAPI(barberId, date) {
    if (window.KortzenAPI) {
        try {
            const result = await KortzenAPI.getBarberSchedule(barberId, date);
            if (result && result.slots) {
                return result.slots;
            }
        } catch (e) {
            console.warn('⚠️ Schedule API not available:', e);
        }
    }
    return null;
}

// Generate local schedule (fallback)
function generateLocalSchedule() {
    const timeSlots = ["10:00", "11:00", "12:00", "13:00", "16:00", "17:00", "18:00", "19:00"];
    const schedule = {};

    BARBERS.forEach(barber => {
        schedule[barber.id] = {};

        NEXT_14_DAYS.forEach(day => {
            const dayOfWeek = day.dayOfWeek;
            const worksThisDay = barber.workingDays.includes(dayOfWeek);

            if (!worksThisDay) {
                schedule[barber.id][day.dateString] = [];
                return;
            }

            const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

            schedule[barber.id][day.dateString] = timeSlots.map(time => {
                const busyChance = isWeekend ? 0.6 : 0.3;
                const isOccupied = Math.random() < busyChance;

                return {
                    time: time,
                    available: !isOccupied
                };
            });
        });
    });

    return schedule;
}

// Initialize booking data
async function initBookingData() {
    // Load barbers (try API first)
    BARBERS = await loadBarbersFromAPI();

    // Generate schedules locally (API will be used per-request if available)
    SCHEDULES = generateLocalSchedule();

    console.log('✅ Booking Data Initialized:', BARBERS.length, 'barbers');
}

// Sync initialization (for compatibility with booking-wizard.js)
function initBookingDataSync() {
    BARBERS = loadBarbersFromLocalStorage();
    SCHEDULES = generateLocalSchedule();
    console.log('✅ Booking Data Loaded (sync):', BARBERS.length, 'barbers');
}

// Initialize synchronously first for immediate availability
initBookingDataSync();

// Then try API asynchronously to update
if (window.KortzenAPI) {
    initBookingData().catch(console.error);
}
