# Migration Guide — v1.3.0

## Summary
Starting with **v1.3.0**, Contactulus is **self-contained** under your site at `/contactulus/`.  
No external host is required. You now install two files:

- `/contactulus/contactulus.js`
- `/contactulus/contact.php`

The JS injects scoped default CSS and does light UX (validation banner).  
The PHP handler is minimal, POST-only, and redirects to `/thank-you.html` (PRG).

---

## What Changed (since v1.2.0)

### Front-end
- **Was:** a single *hosted* script at `https://service.conram.it/.../contactulus.js`.
- **Now:** a single **local** script at `/contactulus/contactulus.js`.  
  (Scoped CSS remains injected; thank-you CSS removed from JS.)

### Back-end
- **Was:** remote `/svc` endpoint.
- **Now:** **local** endpoint: `/contactulus/contact.php`.
  - Honeypot, simple render-time trap, form key.
  - Basic field validation.
  - **303** redirect to `/thank-you.html` on success (PRG).
  - Sends **admin mail** + **submitter receipt** (full copy).
  - Uses **envelope sender**: `-f form-engine@conram.it` for both mails (SPF/DMARC aligned).

### Personalization
- Thank-you personalization via `sessionStorage` **remains in code** but is **optional**.

---

## Upgrade Steps (one step at a time)

1) **Copy files**
   - Add these to your site:
     ```
     /contactulus/contactulus.js
     /contactulus/contact.php
     ```

2) **Update the form page**
   - Ensure the form’s `action` targets the local handler:
     ```html
     <form class="cf" action="https://www.conram.it/contactulus/contact.php" method="POST" accept-charset="utf-8">
     ```
   - Include the JS once (after the form is fine):
     ```html
     <script src="/contactulus/contactulus.js" defer></script>
     ```
   - Keep the hidden inputs:
     ```html
     <input type="hidden" name="form_key" value="conram_v1_2025_09">
     <input type="hidden" name="render_ts" value="">
     <script>document.currentScript.previousElementSibling.value = String(Date.now());</script>
     ```

3) **Configure `contact.php`**
   - Open the file and set:
     ```php
     $to       = 'you@conram.it';
     $from     = 'form-engine@conram.it'; // must be your domain
     $siteName = 'CONRAM.IT';
     ```
   - (Optional) If you show a site link in the confirmation:
     ```php
     $brandLabel = 'Conram';
     $siteUrl    = 'https://www.conram.it';
     ```

4) **Thank-you page**
   - Confirm that `/thank-you.html` exists.

5) **Mail deliverability**
   - Keep **SPF/DMARC/DKIM** for `conram.it`.
   - We now pass the **envelope sender** (`-f form-engine@conram.it`) on **both** mails.

---

## Backward Compatibility

- The **hosted** script at `https://service.conram.it/.../contactulus.js` is **deprecated**.  
  Migrate to the **local** `/contactulus/contactulus.js`.
- Thank-you personalization via `sessionStorage` is **optional**.  
  It only activates if your thank-you page includes the expected container; otherwise it quietly does nothing.

---

## Example Form Snippet

```html
<section class="contactulus-wrapper">
  <h2>Contact</h2>

  <form class="cf" action="/contactulus/contact.php" method="POST" accept-charset="utf-8">
    <div id="cf-error" class="cf-error" hidden></div>

    <div>
      <label for="fullname">Name</label>
      <input id="fullname" name="fullname" required maxlength="120" autocomplete="name" title="Max 120 characters">
    </div>

    <div>
      <label for="email">Email</label>
      <input id="email" name="email" type="email" required maxlength="160"
             autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false"
             pattern="^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$"
             title="Use a standard ASCII email address (no accented characters)">
    </div>

    <div>
      <label for="subject">Subject</label>
      <input id="subject" name="subject" maxlength="140" title="Max 140 characters">
    </div>

    <div>
      <label for="phone">Phone (optional)</label>
      <input id="phone" name="phone" type="tel" maxlength="32"
             pattern="^\+?[0-9\s\-\(\)/]{6,32}$" autocomplete="tel" inputmode="tel"
             title="/ and - and + are usable with up to 64 numbers">
    </div>

    <div class="cf__span-2">
      <label for="message">Message</label>
      <textarea id="message" name="message" required rows="6" maxlength="4000" title="Max 4000 characters"></textarea>
    </div>

    <!-- Honeypot -->
    <div class="hp" aria-hidden="true">
      <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
    </div>

    <!-- Hidden tokens -->
    <input type="hidden" name="form_key" value="conram_v1_2025_09">
    <input type="hidden" name="render_ts" value="">
    <script>document.currentScript.previousElementSibling.value = String(Date.now());</script>

    <div class="cf__actions cf__span-2">
      <button type="submit">Send Message</button>
    </div>
  </form>

  <script src="/contactulus/contactulus.js" defer></script>
</section>
