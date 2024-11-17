# Interactive Map with Compass and Markers

This project implements an interactive map for a ArmA Reforger, allowing users to:
- Place and manage markers on the map.
- Use an interactive compass to adjust the north of the map to the desired direction.
- Add new marker categories dynamically from the menu.
- Control markers with functions like "Undo Last" or "Delete All".

[ReforgerMap](https://reforgermap.com)

---

## **Main Features**
- **Interactive Markers**:
- Select a marker type from the menu and place it on the map.
- Feature to automatically disable marker selection after placing them.
- Add custom marker categories directly from the interface.

- **Marker Management**:
- **Undo Last**: Removes the last marker placed.
- **Delete All**: Clears all markers from the map.

- **Interactive Compass**:
- Rotate the map to set north to any desired direction (N, E, S, W).
- Compass displays current direction.

## **TO-DO**
- Share private Marks and planners

---

## **Requirements**
- [Leaflet.js](https://leafletjs.com/): JavaScript library for interactive maps.
- Custom icon files for markers:
- `icon-enemy.png`
- `icon-exit.png`
- `icon-respawn.png`
- `icon-loot.png`

---

## **How ​​to use**
1. **Clone this repository**:
```bash
git clone https://github.com/SergeWinters/reforgermap.git
cd reforgermap