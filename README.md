# CZ Volume

**CZ Volume** è un plugin WordPress per gestire raccolte di articoli chiamate **Volumi**.

Ogni Volume è un CPT (`volume`) e può contenere più Post (capitoli) con:

- numero capitolo per volume,
- posizione ordinabile,
- flag `Volume Principale` per identificare il volume di riferimento del capitolo.

Lo stesso Post può appartenere a più Volumi con numerazioni diverse.

---

## Funzionalità principali

- CPT `volume` pubblico con archivio `/volumi` e supporto REST.
- Tabella custom `{$wpdb->prefix}cz_volume_items` per relazioni volume/capitolo.
- Gestione capitoli da admin con `WP_List_Table`:
  - aggiunta/rimozione,
  - ordinamento drag&drop,
  - salvataggio AJAX.
- API REST custom (`cz-volume/v1`) per leggere e gestire relazioni.
- Caching con transient (`12h`) e invalidazione automatica.
- Metabox su `post` per assegnare il post ai volumi con UI a token/chips.
- Regola `Volume Principale` lato metabox post: il primo volume selezionato viene impostato come principale.
- Metabox su `volume` per:
  - copertina,
  - file EPUB,
  - file PDF,
  - switch `Completato`.
- Cleanup controllato in deactivation/uninstall.
- Caricamento asset con preferenza per file minificati (`.min.js/.min.css`) quando disponibili.

---

## Requisiti

- WordPress 6.0+
- PHP 7.4+

---

## Installazione

1. Copia la cartella `cz-volume` in `wp-content/plugins/`.
2. Attiva il plugin da **Plugin > Plugin installati**.
3. Vai in **Volumi** per creare i volumi.
4. Usa **Volumi > Gestione Capitoli** per gestire i capitoli.

---

## Endpoint REST

Namespace: `cz-volume/v1`

- `GET /volume/{id}/chapters`
- `GET /post/{id}/volumes`
- `POST /volume/{id}/chapter`
- `DELETE /volume/{id}/chapter/{post_id}`

---

## Build asset minificati

```bash
bash script/build-assets.sh
```

Il comando genera automaticamente:

- `assets/admin.min.css`
- `assets/admin.min.js`

Il plugin carica i file minificati quando `SCRIPT_DEBUG` è `false`, con fallback automatico ai file non minificati.

---

## Note

- Deactivation: di default non elimina i dati, ma può pulire cache transient via filtro/costante.
- Uninstall: la rimozione dati è disattivata di default e va esplicitamente abilitata.
