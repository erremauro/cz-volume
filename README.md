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
- Navigazione "Capitolo Precedente/Successivo" aggiunta automaticamente dopo il blocco "Pagine" dei post che appartengono a un Volume, con shortcode `[cz_volume_chapters_nav]` per il posizionamento manuale.

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

## Navigazione Capitoli (Precedente/Successivo)

Nei Post che appartengono a un Volume, i link "Capitolo Precedente" e "Capitolo Successivo" vengono **aggiunti automaticamente subito dopo il blocco "Pagine"** (`wp_link_pages`, se il tema lo stampa), in base all'ordine dei capitoli nel Volume Principale del post. Non serve alcuna azione editoriale, e non serve modificare i template del tema.

Note comuni (append automatico e shortcode):

- Se il post non appartiene a nessun Volume, o è l'unico capitolo, non viene stampato nulla.
- Il titolo mostrato nei link usa il campo `Mostra Come` del post (se impostato), altrimenti il titolo del post.
- Vengono collegati solo capitoli il cui post è pubblicato.
- Lo stile (`assets/frontend.css`) è caricato solo nelle pagine dove la navigazione viene effettivamente mostrata.

Per disattivare l'append automatico (globalmente o per singolo post):

```php
// Disattiva ovunque.
add_filter( 'cz_volume_auto_append_nav', '__return_false' );

// Disattiva solo per alcuni post.
add_filter( 'cz_volume_auto_append_nav', function ( $enabled, $post_id ) {
    if ( in_array( $post_id, array( 123, 456 ), true ) ) {
        return false;
    }
    return $enabled;
}, 10, 2 );
```

### Shortcode `[cz_volume_chapters_nav]`

Se preferisci posizionare la navigazione manualmente in un punto specifico del contenuto (invece che in fondo), inserisci lo shortcode dove vuoi: l'append automatico rileva la presenza dello shortcode già renderizzato e non duplica la navigazione.

Attributi opzionali:

- `volume_id` — forza un Volume specifico invece del Volume Principale del post corrente.
- `post_id` — forza il post di riferimento invece di quello corrente (utile in template custom).
- `prev_label` — testo del link precedente (default: "Capitolo Precedente").
- `next_label` — testo del link successivo (default: "Capitolo Successivo").

Esempio:

```
[cz_volume_chapters_nav]
```

```
[cz_volume_chapters_nav prev_label="« Prima" next_label="Dopo »"]
```

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
