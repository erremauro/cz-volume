# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.1] 2026-02-13
### Fixed
- Ricerca in `Elenco Post` aggiornata con match su `Titolo + Autore`

## [1.2.0] 2026-02-13
### Added
- Aggiunto Campo `Sottotitolo` nella metabox del Volume `Dettagli Volume`
### Changed
- Modifiche apportate alla tabella in `Gestione Capitoli > Elenco Post`:
  - Aggiunta la possibilità di Filtrare per `Disponibilità`
  - Aggiunto ordinamento per `Titolo` e `Autore`
  - Resa fissa la barra delle colonne
  - `Numero Capitolo` impostato automaticamente al Capitolo Successivo
  - `Volume Principale` deflaggato per default se il capitolo ha già un Volume Principale
  

## [1.1.1] 2026-02-13
### Changed
- Icona menu dashboard del CPT `volume` impostata a `dashicons-book`.

## [1.1.0] 2026-02-13
### Added
- Nuova metabox `Volumi` nei Post con selezione a token/chips, suggerimenti live e link `Più utilizzati`.
- Sincronizzazione relazioni volume/post al salvataggio del Post:
  - rimozione dalle relazioni non più selezionate,
  - aggiunta in coda nei nuovi volumi selezionati.
- Regola `Volume Principale` lato metabox Post: il primo volume selezionato viene impostato come principale.
- Nuova metabox `Dettagli Volume` con:
  - upload copertina,
  - upload EPUB,
  - upload PDF,
  - switch `Completato`.
- Azione `Gestisci` nella lista admin dei Volumi, con accesso diretto a `Gestione Capitoli` sul volume selezionato.
- Script `script/build-assets.sh` per minificazione asset plugin.

### Changed
- Migrazione schema DB con nuova colonna `is_primary` per la relazione capitolo/volume.
- Gestione `Volume Principale` esclusiva: un solo volume principale per singolo post.
- Enqueue asset admin aggiornato con preferenza automatica per `.min.js/.min.css` e fallback ai file sorgente.
- Supporto `author` aggiunto al CPT `volume`.

### Fixed
- Miglioramenti UI/UX nelle schermate admin (selezione righe, suggerimenti, bordi/dropdown, stati visuali).

## [1.0.0] 2026-02-12
### Added
- Initial release.
- CPT `volume` con archivio `/volumi`.
- Tabella custom `cz_volume_items` per gestione capitoli.
- Gestione capitoli admin (`WP_List_Table`) con AJAX e drag&drop.
- REST API `cz-volume/v1`.
- Cache transient con invalidazione su modifica.
- Cleanup controllato in deactivation/uninstall.


[Unreleased]: https://github.com/erremauro/cz-volume/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/erremauro/cz-volume/releases/tag/v1.2.0
[1.1.1]: https://github.com/erremauro/cz-volume/releases/tag/v1.1.1
[1.1.0]: https://github.com/erremauro/cz-volume/releases/tag/v1.1.0
[1.0.0]: https://github.com/erremauro/cz-volume/releases/tag/v1.0.0
