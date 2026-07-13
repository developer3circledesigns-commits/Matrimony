from playwright.sync_api import sync_playwright

URL = "http://localhost/profile_matches.php"

with sync_playwright() as p:

    browser = p.chromium.launch(headless=False)

    page = browser.new_page(
        viewport={"width": 1920, "height": 1080}
    )

    page.goto(URL)
    page.wait_for_load_state("networkidle")

    footer = page.locator("footer")

    if footer.count() == 0:
        print("❌ Footer not found")
        browser.close()
        exit()

    print("✅ Footer Found")

    box = footer.bounding_box()

    if box:

        print(f"Footer Width : {box['width']}")
        print(f"Footer Height : {box['height']}")

        if box["width"] < page.viewport_size["width"]:
            print("❌ Footer does not span full width")
            print("Recommendation: width:100%")

    # Links
    links = footer.locator("a").all()

    print(f"\nLinks : {len(links)}")

    for i, link in enumerate(links):

        href = link.get_attribute("href")

        if not href:
            print(f"❌ Link {i+1} missing href")

        box = link.bounding_box()

        if box:

            if box["height"] < 44 or box["width"] < 44:
                print(f"⚠ Link {i+1} touch area too small")

    # Images
    images = footer.locator("img").all()

    for i, img in enumerate(images):

        alt = img.get_attribute("alt")

        if not alt:
            print(f"⚠ Image {i+1} missing ALT")

        broken = img.evaluate("""
        img=>!img.complete || img.naturalWidth==0
        """)

        if broken:
            print(f"❌ Broken image {i+1}")

    # Buttons
    buttons = footer.locator("button").all()

    for i, btn in enumerate(buttons):

        box = btn.bounding_box()

        if box:

            if box["height"] < 44:
                print(f"⚠ Button {i+1} too small")

    # Overflow
    overflow = footer.evaluate("""
    e=>e.scrollWidth>e.clientWidth
    """)

    if overflow:
        print("❌ Footer horizontal overflow")

    # Hidden Elements
    hidden = footer.locator("*").evaluate_all("""
    els=>els.filter(e=>{
        let s=getComputedStyle(e);
        return s.display=='none'||s.visibility=='hidden';
    }).length
    """)

    if hidden:
        print(f"⚠ Hidden Elements : {hidden}")

    # Screenshot
    footer.screenshot(path="footer.png")

    print("\nFooter screenshot saved.")

    browser.close()