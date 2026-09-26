<?php
declare(strict_types=1);

/**
 * Setzt den Klartext einer System-Mail (Vorlage aus Notifier::TEMPLATES bzw. Admin-Vorlage) in das
 * Erscheinungsbild des Schichtplaners: ein Bon auf dem dunklen Tresen ("Der Bon-Strang", siehe
 * DESIGN.md). Die Vorlagen bleiben reiner Text; das Layout erkennt darin nur wenige Muster:
 *
 *   - "Hallo Anna,"           einzeilig am Anfang: wird zur Überschrift
 *   - "Zeit: 16:00-22:00"     Absatz aus "Bezeichnung: Wert"-Zeilen: wird zum Bon-Block
 *                             (Zeiten, Datum, Zugangsdaten in Monospace)
 *   - https://...             Absatz nur aus einer Adresse: wird zum Button (Beschriftung aus $cta)
 *
 * E-Mail-Clients verstehen kein modernes CSS, daher Tabellen und Inline-Styles; Lochreihe und
 * Zickzack-Kante sind Zugabe (Farbverlauf), ohne sie bleibt eine gerade Papierkante.
 */
final class MailLayout
{
    // Tokens aus DESIGN.md (public/partials/header.php).
    private const PAPER = '#faf3e3';
    private const SURROUND = '#332b1c';
    private const INK = '#1c1917';
    private const INK_SOFT = '#6b6459';
    private const LINE = '#ddd4bf';
    private const LINE_STRONG = '#b9ad91';
    private const STAMP = '#c8391f';
    private const SURROUND_INK = '#f3e8d3';
    private const SURROUND_INK_SOFT = '#a8967a';

    private const SANS = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    private const MONO = "ui-monospace, 'SF Mono', 'Roboto Mono', Menlo, Consolas, monospace";

    /** Bezeichnungen, deren Wert eine Zeit/ein Datum/ein Zugangsdatum ist - kommt in Monospace. */
    private const MONO_LABEL = '/zeit|datum|^tag$|passwort|e-mail|adresse/iu';
    /** Bezeichnungen, die einen einzeiligen "X: Y"-Absatz sicher als Bon-Zeile ausweisen. */
    private const KV_LABEL = '/passwort|e-mail|schicht|datum|^tag$|zeit|^ort$/iu';

    /**
     * @param array{label:string,path?:string,url?:string,variant?:string}|null $cta Button; url hat Vorrang vor path
     */
    public static function html(string $subject, string $body, ?array $cta, string $appName, string $portalUrl): string
    {
        $blocks = self::parse($body);
        $ctaUrl = self::ctaUrl($cta, $portalUrl);
        $stamp = ($cta['variant'] ?? 'ink') === 'stamp';

        $hasButton = false;
        $inner = '';
        $preheader = '';
        foreach ($blocks as $b) {
            switch ($b['type']) {
                case 'greeting':
                    $inner .= '<tr><td style="padding:0 0 14px 0;font:700 21px/1.25 ' . self::SANS . ';letter-spacing:-0.01em;color:' . self::INK . ';">' . self::e($b['text']) . '</td></tr>';
                    break;
                case 'kv':
                    $inner .= self::kvBlock($b['rows']);
                    break;
                case 'url':
                    $inner .= self::button($cta['label'] ?? 'Zum Schichtplaner', $b['text'], $stamp);
                    $hasButton = true;
                    break;
                default:
                    if ($preheader === '') {
                        $preheader = mb_substr(preg_replace('/\s+/u', ' ', $b['text']) ?? '', 0, 110);
                    }
                    $inner .= '<tr><td style="padding:0 0 16px 0;font:400 16px/1.55 ' . self::SANS . ';color:' . self::INK . ';">' . self::linkify(self::e($b['text'])) . '</td></tr>';
            }
        }
        if (!$hasButton && $ctaUrl !== null) {
            $inner .= self::button($cta['label'], $ctaUrl, $stamp);
        }

        $profileUrl = $portalUrl !== '' ? rtrim($portalUrl, '/') . '/profile.php' : null;
        $footer = 'Diese Nachricht kommt aus dem ' . self::e($appName) . '. Welche Nachrichten dich erreichen, stellst du unter '
            . ($profileUrl !== null
                ? '<a href="' . self::e($profileUrl) . '" style="color:' . self::SURROUND_INK . ';text-decoration:underline;">Profil</a>'
                : '„Profil“')
            . ' ein.';

        $holes = 'background-color:' . self::PAPER . ';background-image:radial-gradient(circle at 12px 0,' . self::SURROUND . ' 5px,rgba(51,43,28,0) 5.5px);background-size:24px 10px;background-repeat:repeat-x;';
        $tear = 'background-color:' . self::PAPER . ';background-image:linear-gradient(315deg,' . self::SURROUND . ' 25%,rgba(51,43,28,0) 25%),linear-gradient(45deg,' . self::SURROUND . ' 25%,rgba(51,43,28,0) 25%);background-size:12px 12px;background-position:0 2px;background-repeat:repeat-x;';

        return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light">'
            . '<title>' . self::e($subject) . '</title></head>'
            . '<body style="margin:0;padding:0;background:' . self::SURROUND . ';">'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:' . self::SURROUND . ';">' . self::e($preheader) . '&#8199;&#847;&#8199;&#847;&#8199;&#847;</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . self::SURROUND . ';"><tr><td align="center" style="padding:28px 12px 32px 12px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">'
            // Kopf auf dem Tresen: nur der Name des Tools, in der Etiketten-Stimme des Systems.
            . '<tr><td style="padding:0 4px 12px 4px;font:700 12px/1.2 ' . self::SANS . ';letter-spacing:0.08em;text-transform:uppercase;color:' . self::SURROUND_INK . ';">' . self::e($appName) . '</td></tr>'
            // Lochreihe, Bon, Zickzack-Kante.
            . '<tr><td height="10" style="height:10px;line-height:10px;font-size:0;border-radius:2px 2px 0 0;' . $holes . '">&nbsp;</td></tr>'
            . '<tr><td style="background:' . self::PAPER . ';padding:8px 28px 6px 28px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $inner . '</table>'
            . '</td></tr>'
            . '<tr><td height="12" style="height:12px;line-height:12px;font-size:0;border-radius:0 0 2px 2px;' . $tear . '">&nbsp;</td></tr>'
            . '<tr><td style="padding:18px 4px 0 4px;font:400 12px/1.5 ' . self::SANS . ';color:' . self::SURROUND_INK_SOFT . ';">' . $footer . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /** Text-Alternative: der Vorlagentext, dazu die Adresse des Buttons. */
    public static function text(string $body, ?array $cta, string $portalUrl): string
    {
        $text = trim(str_replace("\r\n", "\n", $body));
        $url = self::ctaUrl($cta, $portalUrl);
        if ($url !== null && !str_contains($text, $url)) {
            $text .= "\n\n" . $cta['label'] . ': ' . $url;
        }
        return $text . "\n";
    }

    /** @return list<array{type:string,text?:string,rows?:list<array{0:string,1:string}>}> */
    private static function parse(string $body): array
    {
        $paragraphs = preg_split('/\n{2,}/', trim(str_replace(["\r\n", "\r"], "\n", $body))) ?: [];
        $blocks = [];
        foreach ($paragraphs as $i => $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if ($i === 0 && !str_contains($p, "\n") && preg_match('/^(Hallo|Hi|Liebe[rs]?|Guten\s+\w+)\b.*,$/u', $p)) {
                $blocks[] = ['type' => 'greeting', 'text' => $p];
            } elseif (preg_match('#^https?://\S+$#', $p)) {
                $blocks[] = ['type' => 'url', 'text' => $p];
            } elseif (($rows = self::keyValueRows($p)) !== null) {
                $blocks[] = ['type' => 'kv', 'rows' => $rows];
            } else {
                $blocks[] = ['type' => 'text', 'text' => $p];
            }
        }
        return $blocks;
    }

    /** @return list<array{0:string,1:string}>|null */
    private static function keyValueRows(string $p): ?array
    {
        $rows = [];
        foreach (explode("\n", $p) as $line) {
            if (!preg_match('/^([^:\n]{2,32}):\s+(\S.*)$/u', trim($line), $m)) {
                return null;
            }
            $rows[] = [trim($m[1]), trim($m[2])];
        }
        if (count($rows) < 2 && !preg_match(self::KV_LABEL, $rows[0][0])) {
            return null; // ein einzelner Satz wie "Wichtig: bitte ..." bleibt Fließtext
        }
        return $rows;
    }

    private static function kvBlock(array $rows): string
    {
        $html = '<tr><td style="padding:2px 0 18px 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px dashed ' . self::LINE_STRONG . ';border-bottom:1px dashed ' . self::LINE_STRONG . ';">';
        foreach ($rows as $n => [$label, $value]) {
            $mono = (bool)preg_match(self::MONO_LABEL, $label);
            $valueStyle = $mono
                ? 'font:600 16px/1.35 ' . self::MONO . ';'
                : 'font:700 16px/1.35 ' . self::SANS . ';';
            $rule = $n > 0 ? 'border-top:1px solid ' . self::LINE . ';' : '';
            $html .= '<tr>'
                . '<td valign="baseline" width="34%" style="padding:9px 10px 9px 0;' . $rule . 'font:700 12px/1.3 ' . self::SANS . ';letter-spacing:0.035em;text-transform:uppercase;color:' . self::INK_SOFT . ';">' . self::e($label) . '</td>'
                . '<td valign="baseline" style="padding:9px 0;' . $rule . $valueStyle . 'color:' . self::INK . ';word-break:break-word;">' . self::e($value) . '</td>'
                . '</tr>';
        }
        return $html . '</table></td></tr>';
    }

    private static function button(string $label, string $url, bool $stamp): string
    {
        $bg = $stamp ? self::STAMP : self::INK;
        $fg = $stamp ? '#fff8f4' : self::PAPER;
        return '<tr><td style="padding:6px 0 22px 0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td bgcolor="' . $bg . '" style="border-radius:3px;background:' . $bg . ';">'
            . '<a href="' . self::e($url) . '" style="display:inline-block;padding:14px 22px;font:700 14px/1.2 ' . self::SANS . ';letter-spacing:0.04em;text-transform:uppercase;color:' . $fg . ';text-decoration:none;border-radius:3px;">' . self::e($label) . '</a>'
            . '</td></tr></table></td></tr>';
    }

    private static function ctaUrl(?array $cta, string $portalUrl): ?string
    {
        if ($cta === null) {
            return null;
        }
        if (!empty($cta['url'])) {
            return (string)$cta['url'];
        }
        if ($portalUrl === '') {
            return null;
        }
        return rtrim($portalUrl, '/') . '/' . ltrim((string)($cta['path'] ?? ''), '/');
    }

    /** Bare Adressen im (bereits escapten) Fließtext anklickbar machen. */
    private static function linkify(string $escaped): string
    {
        $linked = preg_replace('#(https?://[^\s<]+)#', '<a href="$1" style="color:' . self::INK . ';text-decoration:underline;">$1</a>', $escaped);
        return nl2br($linked ?? $escaped, false);
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
