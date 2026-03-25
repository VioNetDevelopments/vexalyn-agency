// =========================================
// NEXA AGENCY - MAIN JAVASCRIPT
// =========================================

// INIT LUCIDE ICONS
lucide.createIcons();

// SIDEBAR TOGGLE
const sidebar = document.querySelector('.sidebar');
const toggleBtn = document.querySelector('.toggle-btn');

if(toggleBtn) {
    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
    });
}

// LOGIN TYPING EFFECT
const typeText = (element, text, speed = 30) => {
    let i = 0;
    element.innerHTML = "";
    function type() {
        if (i < text.length) {
            element.innerHTML += text.charAt(i);
            i++;
            setTimeout(type, speed);
        }
    }
    type();
}

// LOGIN PROCESS SIMULATION
const loginForm = document.getElementById('loginForm');
const statusMsg = document.getElementById('statusMsg');

if(loginForm) {
    loginForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const btn = document.querySelector('.btn-system');
        
        btn.disabled = true;
        btn.innerText = "Processing...";
        
        const steps = [
            "Initializing Secure Handshake...",
            "Decrypting Credentials...",
            "Verifying Biometric Hash...",
            "Access Granted."
        ];

        let step = 0;
        const interval = setInterval(() => {
            if(step < steps.length) {
                statusMsg.className = "status-msg status-loading";
                typeText(statusMsg, steps[step]);
                step++;
            } else {
                clearInterval(interval);
                loginForm.submit();
            }
        }, 700);
    });
}

// ADVANCED GLOBE CONFIGURATION
const initGlobe = () => {
    const container = document.getElementById('globe-container');
    if(!container) return;

    const locations = [
        { lat: -6.2088, lng: 106.8456, name: "JAKARTA HQ", size: 1.5, color: "#ffffff" },
        { lat: 35.6762, lng: 139.6503, name: "TOKYO OPS", size: 0.8, color: "#00ff9d" },
        { lat: 51.5074, lng: -0.1278, name: "LONDON STATION", size: 0.8, color: "#00ff9d" },
        { lat: 40.7128, lng: -74.0060, name: "NYC INTEL", size: 0.8, color: "#00ff9d" },
        { lat: 55.7558, lng: 37.6173, name: "MOSCOW SURVEILLANCE", size: 0.6, color: "#ff3b3b" },
        { lat: -33.8688, lng: 151.2093, name: "SYDNEY OUTPOST", size: 0.6, color: "#00ff9d" }
    ];

    const arcs = locations.filter(l => l.name !== "JAKARTA HQ").map(loc => ({
        startLat: -6.2088,
        startLng: 106.8456,
        endLat: loc.lat,
        endLng: loc.lng,
        color: ["rgba(0, 255, 157, 0.2)", "rgba(255, 255, 255, 0.8)"]
    }));

    const world = Globe()
        (container)
        .globeImageUrl('https://unpkg.com/three-globe/example/img/earth-dark.jpg')
        .backgroundImageUrl('https://unpkg.com/three-globe/example/img/night-sky.png')
        .showAtmosphere(true)
        .atmosphereColor('#00ff9d')
        .atmosphereAltitude(0.15)
        .pointsData(locations)
        .pointColor('color')
        .pointAltitude(0.05)
        .pointRadius('size')
        .pointLabel(d => `
            <div style="background: rgba(0,0,0,0.8); padding: 5px 10px; border: 1px solid ${d.color}; border-radius: 4px; color: #fff; font-family: monospace; font-size: 12px;">
                <strong>${d.name}</strong><br>STATUS: ONLINE
            </div>
        `)
        .arcsData(arcs)
        .arcColor('color')
        .arcDashLength(0.4)
        .arcDashGap(0.2)
        .arcDashAnimateTime(2000)
        .arcStroke(0.5)
        .ringsData(locations.filter(l => l.name === "JAKARTA HQ"))
        .ringColor(() => t => `rgba(0, 255, 157, ${1-t})`)
        .ringMaxRadius(5)
        .ringPropagationSpeed(2)
        .ringRepeatPeriod(1000);

    world.controls().autoRotate = true;
    world.controls().autoRotateSpeed = 0.6;
    world.controls().enableZoom = false;
    world.controls().enablePan = false;
    world.pointOfView({ lat: -10, lng: 110, altitude: 2.0 });
}

// Initialize Globe on Load
document.addEventListener('DOMContentLoaded', () => {
    if(document.getElementById('globe-container')) {
        initGlobe();
    }
});

// MESSAGE TIMESTAMP
const formatTime = (date) => {
    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

// AUTO SCROLL CHAT TO BOTTOM
const scrollToBottom = () => {
    const chatMessages = document.querySelector('.chat-messages');
    if(chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}

// SELECT CONTACT IN CHANNEL
const selectContact = (contactId) => {
    document.querySelectorAll('.contact-item').forEach(item => {
        item.classList.remove('active');
    });
    document.querySelector(`.contact-item[data-contact="${contactId}"]`)?.classList.add('active');
}