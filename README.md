# Mapa Interactivo con Brújula y Marcadores

Este proyecto implementa un mapa interactivo para un juego, permitiendo a los usuarios:
- Colocar y gestionar marcadores en el mapa.
- Usar una brújula interactiva para ajustar el norte del mapa según la dirección deseada.
- Añadir nuevas categorías de marcadores dinámicamente desde el menú.
- Controlar los marcadores con funciones como "Deshacer Último" o "Eliminar Todos".

![Mapa Interactivo](https://your-image-link-here.png)

---

## **Características principales**
- **Marcadores interactivos**:
  - Selecciona un tipo de marcador desde el menú y colócalo en el mapa.
  - Función para desactivar automáticamente la selección de marcadores después de colocarlos.
  - Añade categorías personalizadas de marcadores directamente desde la interfaz.

- **Gestión de marcadores**:
  - **Deshacer Último**: Elimina el último marcador colocado.
  - **Eliminar Todos**: Borra todos los marcadores del mapa.

- **Brújula interactiva**:
  - Rotación del mapa para ajustar el norte hacia cualquier dirección deseada (N, E, S, W).
  - La brújula muestra la dirección actual.

---

## **Requisitos**
- [Leaflet.js](https://leafletjs.com/): Librería JavaScript para mapas interactivos.
- Archivos de iconos personalizados para marcadores:
  - `icon-enemy.png`
  - `icon-exit.png`
  - `icon-respawn.png`
  - `icon-loot.png`

---

## **Cómo usar**
1. **Clona este repositorio**:
   ```bash
   git clone https://github.com/tu-usuario/tu-repositorio.git
   cd tu-repositorio
