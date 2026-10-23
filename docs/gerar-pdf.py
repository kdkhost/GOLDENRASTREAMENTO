#!/usr/bin/env python3
"""
Gera o PDF de instalação do Golden Rastreamento a partir de docs/INSTALACAO.md.

    python docs/gerar-pdf.py

O HTML temático é montado num diretório temporário; o único arquivo que sobra é
docs/Golden-Rastreamento-Instalacao.pdf. Precisa de Microsoft Edge ou Google
Chrome instalados (usado em modo headless para imprimir o PDF).
"""
from __future__ import annotations

import html
import os
import re
import subprocess
import sys
import tempfile
from datetime import date
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
MD = RAIZ / "docs" / "INSTALACAO.md"
PDF = RAIZ / "docs" / "Golden-Rastreamento-Instalacao.pdf"

PRETO = "#1c1a17"
DOURADO = "#b8860b"
DOURADO_CLARO = "#e0b64a"
FUNDO_SUAVE = "#faf6ec"
LINHA = "#e3dccb"


def versao() -> str:
    try:
        package = json_loads((RAIZ / "package.json").read_text(encoding="utf-8"))
        return package.get("version", "0.0.0")
    except Exception:
        return "0.0.0"


def json_loads(texto: str) -> dict:
    import json

    return json.loads(texto)


def inline(texto: str) -> str:
    """Escapa o HTML e aplica negrito, código e link do Markdown."""
    saida = html.escape(texto, quote=False)

    def codigo(corpo):
        return f"<code>{corpo}</code>"

    saida = re.sub(r"`([^`]+)`", lambda m: codigo(m.group(1)), saida)
    saida = re.sub(r"\*\*([^*]+)\*\*", r"<strong>\1</strong>", saida)
    saida = re.sub(
        r"\[([^\]]+)\]\(([^)]+)\)",
        r'<a href="\2">\1</a>',
        saida,
    )
    return saida


def blocos(corpo: str) -> str:
    linhas = corpo.split("\n")
    destino: list[str] = []
    i = 0
    while i < len(linhas):
        linha = linhas[i]

        if linha.startswith("```"):
            idioma = linha[3:].strip()
            i += 1
            codigo = []
            while i < len(linhas) and not linhas[i].startswith("```"):
                codigo.append(linhas[i])
                i += 1
            i += 1
            destino.append(
                f'<pre class="{idioma or "texto"}"><code>{html.escape(chr(10).join(codigo))}</code></pre>'
            )
            continue

        if re.fullmatch(r"-{3,}", linha.strip()):
            destino.append("<hr>")
            i += 1
            continue

        m = re.match(r"^(#{1,4})\s+(.*)$", linha)
        if m:
            nivel = len(m.group(1))
            texto = inline(m.group(2))
            id_ = re.sub(r"[^a-z0-9]+", "-", m.group(2).lower()).strip("-")
            destino.append(f"<h{nivel} id=\"{id_}\">{texto}</h{nivel}>")
            i += 1
            continue

        if linha.startswith("|"):
            celulas_tabla = []
            while i < len(linhas) and linhas[i].startswith("|"):
                celulas_tabla.append(linhas[i])
                i += 1
            destino.append(tabela(celulas_tabla))
            continue

        if re.match(r"^- ", linha):
            itens = []
            while i < len(linhas) and re.match(r"^- ", linhas[i]):
                itens.append(inline(linhas[i][2:]))
                i += 1
            destino.append("<ul>" + "".join(f"<li>{x}</li>" for x in itens) + "</ul>")
            continue

        if re.match(r"^\d+\. ", linha):
            itens = []
            while i < len(linhas) and re.match(r"^\d+\. ", linhas[i]):
                itens.append(inline(re.sub(r"^\d+\. ", "", linhas[i])))
                i += 1
            destino.append("<ol>" + "".join(f"<li>{x}</li>" for x in itens) + "</ol>")
            continue

        if linha.startswith("- [ ] "):
            itens = []
            while i < len(linhas) and linhas[i].startswith("- [ ] "):
                itens.append(inline(linhas[i][6:]))
                i += 1
            destino.append(
                '<ul class="check">' + "".join(f"<li>{x}</li>" for x in itens) + "</ul>"
            )
            continue

        if linha.strip() == "":
            i += 1
            continue

        paragrafo = [linha]
        i += 1
        while i < len(linhas) and linhas[i].strip() and not re.match(
            r"^(#|\||- |\d+\. |```|-{3,}| - \[ \] )", linhas[i]
        ):
            paragrafo.append(linhas[i])
            i += 1
        destino.append(f"<p>{inline(' '.join(paragrafo))}</p>")

    return "\n".join(destino)


def tabela(linhas: list[str]) -> str:
    def celulas(linha: str) -> list[str]:
        return [c.strip() for c in linha.strip().strip("|").split("|")]

    cabecalho = celulas(linhas[0])
    corpo = [linhas[i] for i in range(1, len(linhas)) if not re.fullmatch(r"[|\s:\-]+", linhas[i])]
    html_cab = "".join(f"<th>{inline(c)}</th>" for c in cabecalho)
    html_corpo = "".join(
        "<tr>" + "".join(f"<td>{inline(c)}</td>" for c in celulas(linha)) + "</tr>" for linha in corpo
    )
    return f"<table><thead><tr>{html_cab}</tr></thead><tbody>{html_corpo}</tbody></table>"


ESTILO = f"""
@page {{ size: A4; margin: 19mm 17mm 19mm 17mm; }}
* {{ box-sizing: border-box; }}
body {{
  font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
  font-size: 10.4pt; line-height: 1.55; color: {PRETO}; margin: 0;
}}
.capa {{
  background: linear-gradient(135deg, #17140f 0%, #2b2415 55%, #17140f 100%);
  color: #fff; padding: 26mm 18mm; border-radius: 4px; margin-bottom: 12mm;
}}
.capa .marca {{
  letter-spacing: .32em; text-transform: uppercase; font-size: 9pt;
  color: {DOURADO_CLARO}; margin-bottom: 8mm;
}}
.capa h1 {{ font-size: 30pt; line-height: 1.12; margin: 0 0 5mm; color: #fff; font-weight: 600; }}
.capa p {{ color: #e8e0cd; margin: 0 0 9mm; font-size: 11.5pt; max-width: 125mm; }}
.capa .metadados {{ display: flex; flex-wrap: wrap; gap: 3mm 8mm; font-size: 9.2pt; color: #cfc5ab; }}
.capa .metadados span b {{ color: {DOURADO_CLARO}; font-weight: 600; }}
h2 {{
  font-size: 15pt; margin: 11mm 0 3mm; padding-bottom: 2mm;
  border-bottom: 2px solid {DOURADO}; color: #17140f;
  break-after: avoid;
}}
h2 .numero {{ color: {DOURADO}; margin-right: 3mm; }}
h3 {{ font-size: 11.6pt; margin: 6mm 0 2mm; color: #2b2415; break-after: avoid; }}
h4 {{ font-size: 10.6pt; margin: 5mm 0 2mm; break-after: avoid; }}
p {{ margin: 0 0 3mm; }}
hr {{ border: 0; border-top: 1px solid {LINHA}; margin: 6mm 0; }}
ul, ol {{ margin: 0 0 3mm; padding-left: 6.5mm; }}
li {{ margin-bottom: 1.4mm; }}
ul.check li {{ list-style: none; position: relative; padding-left: 6mm; margin-left: -6mm; }}
ul.check li::before {{
  content: ""; position: absolute; left: 0; top: .32em; width: 3.4mm; height: 3.4mm;
  border: 1.4px solid {DOURADO}; border-radius: 2px;
}}
code {{
  overflow-wrap: anywhere;
  font-family: "Cascadia Mono", Consolas, monospace; font-size: 9.2pt;
  background: {FUNDO_SUAVE}; border: 1px solid {LINHA}; border-radius: 3px; padding: 0 1.2mm;
}}
pre {{
  white-space: pre-wrap;
  background: #17140f; color: #f2ead6; padding: 4mm 5mm; border-radius: 4px;
  border-left: 3px solid {DOURADO}; overflow-x: hidden; margin: 0 0 4mm; break-inside: avoid;
}}
pre code {{ background: none; border: 0; color: inherit; padding: 0; font-size: 8.9pt; line-height: 1.5; }}
table {{ width: 100%; border-collapse: collapse; margin: 0 0 4mm; font-size: 9.5pt; break-inside: avoid; }}
th {{
  background: #17140f; color: {DOURADO_CLARO}; text-align: left;
  padding: 2mm 2.6mm; font-weight: 600; font-size: 9.1pt;
}}
td {{ padding: 1.8mm 2.6mm; border-bottom: 1px solid {LINHA}; vertical-align: top; overflow-wrap: anywhere; }}
tbody tr:nth-child(even) td {{ background: {FUNDO_SUAVE}; }}
a {{ color: #7a5c05; text-decoration: none; border-bottom: 1px solid {DOURADO_CLARO}; }}
.rodape {{ margin-top: 10mm; padding-top: 4mm; border-top: 2px solid {DOURADO}; font-size: 9pt; color: #6b6353; }}
.rodape b {{ color: {PRETO}; }}
"""

MODELO = """<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Golden Rastreamento — Instalação</title>
<style>{estilo}</style>
</head>
<body>
<section class="capa">
  <div class="marca">Golden Rastreamento</div>
  <h1>Instalação completa<br>do sistema</h1>
  <p>Site, captação de leads, painel (CRM, CMS e ERP), loja virtual e portal do
     cliente — do primeiro <code>git clone</code> ao checklist de saída.</p>
  <div class="metadados">
    <span>Versão <b>{versao}</b></span>
    <span>Data <b>{data}</b></span>
    <span>Requisito <b>PHP 8.3 + MySQL</b></span>
    <span>Responsável <b>Marcelo · KDKHOST</b></span>
    <span>Contato <b>contato@kdkhost.com.br</b></span>
  </div>
</section>
{corpo}
<div class="rodape">Documento gerado de <b>docs/INSTALACAO.md</b> — a fonte é a
mesma para este PDF e para o README do repositório. Nenhuma credencial aparece
aqui: senha de banco, de painel e <b>APP_KEY</b> ficam apenas no <b>.env</b> do
servidor.</div>
</body>
</html>
"""


def principal() -> int:
    corpo_md = MD.read_text(encoding="utf-8")
    # O título e o sumário de cabeçalho do .md já estão na capa do PDF.
    corpo_md = re.sub(r"\A# .*?\n---\n", "", corpo_md, flags=re.S)

    # Numera os capítulos (os "##" já vêm numerados no texto; mantém como está).
    def capa_numero(m: re.Match) -> str:
        texto = m.group(1)
        numero, resto = (texto.split(" ", 1) + [""])[:2] if texto[0].isdigit() else ("", texto)
        if numero:
            return f'<h2><span class="numero">{numero}</span>{inline(resto)}</h2>'
        return f"<h2>{inline(texto)}</h2>"

    html_corpo = blocos(corpo_md)
    html_corpo = re.sub(
        r"<h2>([0-9]+)\.\s([^<]*)</h2>",
        lambda m: f'<h2><span class="numero">{m.group(1)}</span>{m.group(2)}</h2>',
        html_corpo,
    )

    pagina = MODELO.format(estilo=ESTILO, versao=versao(),
                           data=date.today().strftime("%d/%m/%Y"), corpo=html_corpo)

    navegador = next(
        (caminho for caminho in [
            r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
            r"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
            r"C:\Program Files\Google\Chrome\Application\chrome.exe",
        ] if os.path.exists(caminho)),
        None,
    )
    if not navegador:
        print("Edge ou Chrome não encontrado; PDF não gerado.", file=sys.stderr)
        return 1

    with tempfile.TemporaryDirectory() as pasta:
        alvo = Path(pasta) / "instalacao.html"
        alvo.write_text(pagina, encoding="utf-8")
        resultado = subprocess.run(
            [
                navegador, "--headless", "--disable-gpu", "--no-sandbox",
                f"--user-data-dir={Path(pasta) / 'perfil'}",
                "--no-pdf-header-footer", "--virtual-time-budget=8000",
                f"--print-to-pdf={PDF}", str(alvo),
            ],
            capture_output=True, text=True,
        )
        if resultado.returncode != 0:
            print(resultado.stdout, resultado.stderr, file=sys.stderr)
            return 1

    print(f"PDF gerado: {PDF} ({PDF.stat().st_size // 1024} KB)")
    return 0


if __name__ == "__main__":
    raise SystemExit(principal())
