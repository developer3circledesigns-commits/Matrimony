from playwright.sync_api import sync_playwright
import json
from datetime import datetime

URL = "http://localhost:8080/home"

DEVICES = [
    ("iPhone SE", 375, 667),
    ("iPhone 12", 390, 844),
    ("iPhone 14 Pro Max", 430, 932),
    ("Pixel 7", 412, 915),
    ("Samsung Galaxy S22", 360, 780),
    ("iPad Mini", 768, 1024)
]

report = []

with sync_playwright() as p:

    browser = p.chromium.launch(headless=False)

    for device, width, height in DEVICES:

        print("=" * 80)
        print(device)

        context = browser.new_context(
            viewport={"width": width, "height": height}
        )

        page = context.new_page()

        page.goto(URL)

        page.wait_for_load_state("networkidle")

        footer = page.locator("footer")

        if footer.count() == 0:

            report.append({
                "device": device,
                "issue": "Footer Not Found",
                "recommendation": "Add semantic <footer> element."
            })

            continue

        footer.scroll_into_view_if_needed()

        footer.screenshot(path=f"footer_{device}.png")

        box = footer.bounding_box()

        # -------------------------
        # Width
        # -------------------------

        if box["width"] < width:

            report.append({
                "device": device,
                "issue": "Footer not full width",
                "recommendation":
                "Use width:100%; display:block;"
            })

        # -------------------------
        # Horizontal Scroll
        # -------------------------

        overflow = page.evaluate("""
        () => document.documentElement.scrollWidth >
              document.documentElement.clientWidth
        """)

        if overflow:

            report.append({
                "device": device,
                "issue": "Horizontal scrolling",
                "recommendation":
                "Remove fixed widths and use max-width:100%."
            })

        # -------------------------
        # Images
        # -------------------------

        images = footer.locator("img").all()

        for i, img in enumerate(images):

            img_box = img.bounding_box()

            if img_box:

                if img_box["width"] > width:

                    report.append({
                        "device": device,
                        "issue": f"Image {i+1} overflow",
                        "recommendation":
                        "max-width:100%; height:auto;"
                    })

        # -------------------------
        # Buttons
        # -------------------------

        buttons = footer.locator("button,a").all()

        for i, btn in enumerate(buttons):

            btn_box = btn.bounding_box()

            if btn_box:

                if btn_box["height"] < 44:

                    report.append({
                        "device": device,
                        "issue": f"Button {i+1} too small",
                        "recommendation":
                        "Minimum size 44x44px."
                    })

        # -------------------------
        # Text Overflow
        # -------------------------

        overflowing = footer.evaluate("""
        footer=>{

            let bad=[];

            footer.querySelectorAll("*").forEach(el=>{

                let r=el.getBoundingClientRect();

                if(r.right>window.innerWidth){

                    bad.push(el.tagName);

                }

            });

            return bad.length;

        }
        """)

        if overflowing:

            report.append({
                "device": device,
                "issue": "Overflowing elements",
                "recommendation":
                "Allow wrapping using flex-wrap or grid."
            })

        # -------------------------
        # Hidden Elements
        # -------------------------

        hidden = footer.locator("*").evaluate_all("""
        els=>els.filter(e=>{
        let s=getComputedStyle(e);
        return s.display=='none'||s.visibility=='hidden';
        }).length
        """)

        if hidden:

            report.append({
                "device": device,
                "issue": f"{hidden} hidden elements",
                "recommendation":
                "Check media queries."
            })

        # -------------------------
        # Links
        # -------------------------

        links = footer.locator("a").count()

        if links == 0:

            report.append({
                "device": device,
                "issue": "No footer links",
                "recommendation":
                "Verify footer content."
            })

        context.close()

    browser.close()

print("\n")

print("=" * 80)
print("FOOTER RESPONSIVE REPORT")
print("=" * 80)

for item in report:

    print("\nDevice :", item["device"])
    print("Issue :", item["issue"])
    print("Recommendation :", item["recommendation"])

with open("footer_report.json","w") as f:

    json.dump(report,f,indent=4)

print("\nJSON report generated.")