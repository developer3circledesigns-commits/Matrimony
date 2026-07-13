from playwright.sync_api import sync_playwright

URL = "http://localhost:8080/about"

VIEWPORTS = [
    ("Desktop", 1920, 1080),
    ("Laptop", 1366, 768),
    ("Tablet", 768, 1024),
    ("Mobile", 390, 844),
    ("Small Mobile", 360, 640),
]


def audit(page):

    issues = []

    # ----------------------------------------
    # Horizontal Scroll
    # ----------------------------------------

    overflow = page.evaluate("""
    () => document.documentElement.scrollWidth >
          document.documentElement.clientWidth
    """)

    if overflow:
        issues.append((
            "Horizontal Scrolling",
            "Remove fixed widths. Use width:100%, max-width:100%, overflow-x:hidden."
        ))

    # ----------------------------------------
    # Images
    # ----------------------------------------

    images = page.locator("img").all()

    for i, img in enumerate(images):

        box = img.bounding_box()

        if not box:
            continue

        if box["width"] > page.viewport_size["width"]:

            issues.append((
                f"Image {i}",
                "Image exceeds viewport. Add max-width:100%; height:auto;"
            ))

    # ----------------------------------------
    # Buttons
    # ----------------------------------------

    buttons = page.locator("button,a.btn,input[type=submit]").all()

    for i, btn in enumerate(buttons):

        box = btn.bounding_box()

        if not box:
            continue

        if box["height"] < 44:

            issues.append((
                f"Button {i}",
                "Increase touch target to minimum 44x44 pixels."
            ))

    # ----------------------------------------
    # Cards
    # ----------------------------------------

    cards = page.locator(".card").all()

    for i, card in enumerate(cards):

        box = card.bounding_box()

        if box and box["width"] > page.viewport_size["width"]:

            issues.append((
                f"Card {i}",
                "Card wider than viewport."
            ))

    # ----------------------------------------
    # Inputs
    # ----------------------------------------

    inputs = page.locator("input,select,textarea").all()

    for i, inp in enumerate(inputs):

        box = inp.bounding_box()

        if not box:
            continue

        if box["height"] < 40:

            issues.append((
                f"Input {i}",
                "Increase input height for easier interaction."
            ))

    # ----------------------------------------
    # Tables
    # ----------------------------------------

    tables = page.locator("table").all()

    for i, table in enumerate(tables):

        width = table.evaluate(
            "e=>e.scrollWidth>e.clientWidth"
        )

        if width:

            issues.append((
                f"Table {i}",
                "Wrap table inside .table-responsive."
            ))

    # ----------------------------------------
    # Broken Images
    # ----------------------------------------

    broken = page.evaluate("""
    () => [...document.images]
          .filter(i=>!i.complete || i.naturalWidth==0)
          .length
    """)

    if broken:

        issues.append((
            "Broken Images",
            f"{broken} image(s) failed to load."
        ))

    # ----------------------------------------
    # Missing ALT
    # ----------------------------------------

    missing = page.locator("img:not([alt])").count()

    if missing:

        issues.append((
            "Accessibility",
            f"{missing} image(s) missing alt attribute."
        ))

    # ----------------------------------------
    # Console Errors
    # ----------------------------------------

    errors = []

    def log(msg):
        if msg.type == "error":
            errors.append(msg.text)

    page.on("console", log)

    page.wait_for_timeout(1000)

    if errors:

        issues.append((
            "JavaScript",
            "Console errors detected."
        ))

    # ----------------------------------------
    # Overflowing Elements
    # ----------------------------------------

    overflowing = page.evaluate("""
    () => {

        let bad=[];

        document.querySelectorAll("*").forEach(el=>{

            let r=el.getBoundingClientRect();

            if(r.right>window.innerWidth+2){

                bad.push(el.tagName);

            }

        });

        return bad.length;

    }
    """)

    if overflowing:

        issues.append((
            "Overflowing Elements",
            f"{overflowing} element(s) extend beyond viewport."
        ))

    return issues


with sync_playwright() as p:

    browser = p.chromium.launch(headless=False)

    for device, w, h in VIEWPORTS:

        print("\n")
        print("=" * 80)
        print(device)
        print("=" * 80)

        context = browser.new_context(
            viewport={"width": w, "height": h}
        )

        page = context.new_page()

        page.goto(URL)

        page.wait_for_load_state("networkidle")

        page.screenshot(
            path=f"{device}.png",
            full_page=True
        )

        issues = audit(page)

        if not issues:
            print("✓ No major issues found.")

        for title, recommendation in issues:

            print(f"\n❌ {title}")
            print(f"   Recommendation: {recommendation}")

        context.close()

    browser.close()