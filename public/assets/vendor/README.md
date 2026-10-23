# Bundled vendor assets

Served locally so HQ works on shared hosting without third-party CDNs and under a
`'self'`-only Content-Security-Policy.

| Package | Version | Path | License |
|---|---|---|---|
| Font Awesome Free | 6.5.2 | `fontawesome/` | Icons CC BY 4.0, fonts OFL 1.1, code MIT (`LICENSE.txt`) |
| CodeMirror | 5.65.16 | `codemirror/` | MIT |
| Monaco Editor | 0.52.2 | `monaco/vs/` | MIT |

To upgrade, `npm pack <pkg>@<version>`, then copy the same files over.

HQ text fonts (Inter etc.) are already self-hosted in `/assets/fonts/`.
