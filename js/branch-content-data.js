/**
 * KORTZEN - Branch Content Data
 * Datos específicos de cada sucursal (barberos, fotos, información adicional)
 * El usuario debe agregar las fotos reales de los barberos en /assets/images/branches/
 */

const BRANCH_CONTENT = {
    1: { // KORTZEN Gran Vía
        id: 1,
        name: "KORTZEN Gran Vía",
        team: [
            {
                name: "Antonio Kortzen",
                role: "Fundador & Maestro Barbero",
                bio: "15 años de experiencia. Formado en Vidal Sassoon Academy.",
                image: "/assets/images/branches/gran-via/barber-1.jpg" // Usuario agregará foto aquí
            },
            {
                name: "Carlos Méndez",
                role: "Maestro Barbero Senior",
                bio: "Especialista en afeitado tradicional. Campeón nacional 2019 y 2021.",
                image: "/assets/images/branches/gran-via/barber-2.jpg" // Usuario agregará foto aquí
            },
            {
                name: "Miguel Ángel Torres",
                role: "Barbero & Estilista",
                bio: "Formación en Schorem Barber School de Rotterdam.",
                image: "/assets/images/branches/gran-via/barber-3.jpg" // Usuario agregará foto aquí
            }
        ]
    },
    2: { // KORTZEN Salamanca
        id: 2,
        name: "KORTZEN Salamanca",
        team: [
            {
                name: "Rafael Vega",
                role: "Director & Maestro Barbero",
                bio: "20 años de experiencia. Especialista en cortes clásicos y estilo vintage.",
                image: "/assets/images/branches/salamanca/barber-1.jpg" // Usuario agregará foto aquí
            },
            {
                name: "Diego Ruiz",
                role: "Barbero Especialista",
                bio: "Experto en diseño de barba y cuidado facial premium.",
                image: "/assets/images/branches/salamanca/barber-2.jpg" // Usuario agregará foto aquí
            },
            {
                name: "Javier Moreno",
                role: "Estilista Creativo",
                bio: "Tendencias modernas con técnicas tradicionales. Formado en Londres.",
                image: "/assets/images/branches/salamanca/barber-3.jpg" // Usuario agregará foto aquí
            }
        ]
    }
};

// Exportar para uso en otros scripts
if (typeof window !== 'undefined') {
    window.BRANCH_CONTENT = BRANCH_CONTENT;
}
