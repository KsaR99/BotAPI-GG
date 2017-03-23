# BotAPI-GG
## Propozycje zmian bibliotek platformy BotAPI GG ( https://boty.gg.pl )

---

v.2.5:

* Wymagane PHP 5.4+
* Optymalizacje
* Skasowanie deporcjonowanych zmiennych, metod. ("Opisy graficzne")
* $m->r, $m->g, $m->b od teraz jest w tablicy $m->rgb[RR, GG, BB]

----

v. 3.0:

### MessageBuilder
#### Ogólne:
 * Skasowano lokalne stałe które nie będą więcej potrzebne: FORMAT_NONE, FORMAT_BOLD_TEXT, FORMAT_ITALIC_TEXT, FORMAT_UNDERLINE_TEXT, FORMAT_NEW_LINE
 * Kod powiązany z BBcode skasowany.
#### Metody:
 * Skasowano metody: [addBBcode(), setSendToOffline() - nie wspierane już]
 * addText() parametry powiązane z BBcode skasowane.

### PushConnection
#### Ogólne:
 * Stałe lokalne STATUS_* przeniesione do klasy, można się do ich zewnętrznie odwołać używając PushConnection::STATUS_*
#### Metody:
 ***push()***
  * skasowano kod z $count - wygląda na nieużywane i jest nieudokumentowane.
  * skasowano $message->sendToOffline (nie działa już)

 ***setStatus()***
  * coś jak wyżej (1) - skasowano $r.
