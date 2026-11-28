---
paths:
  - 'resources/views/**/*.blade.php'
---

# Views

## Never put form submit buttons inside <x-modal> footer slot
Blade extracts slot content, so <x-slot:footer> renders in the modal's footer bar OUTSIDE the <form> in the final DOM. A type=submit / wire:submit button there never fires. Put Cancel/Save buttons at the end of the <form> body instead (wire:submit on the form handles Enter key too).
