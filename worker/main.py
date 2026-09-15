import time
import requests
from playwright.sync_api import sync_playwright

API_BASE_URL = "http://localhost:8080/web/api/index.php"
WORKER_TOKEN = "424086ec6b7a30789ac9dca09cb51f29"

HEADERS = {
    "Authorization": f"Bearer {WORKER_TOKEN}"
}

def send_heartbeat(status="authenticated"):
    try:
        requests.post(f"{API_BASE_URL}?action=heartbeat", data={"whatsapp_status": status}, headers=HEADERS, timeout=5)
    except Exception as e:
        print(f"[Worker] Heartbeat error: {e}")

def claim_job():
    
    try:
        resp = requests.post(f"{API_BASE_URL}?action=claim_job", headers=HEADERS, timeout=5).json()
        if resp.get("success"):
            return resp.get("data", {}).get("job")
    except Exception as e:
        print(f"[Worker] Claim job error: {e}")
        
    return None

def report_job(job_id, status, error_info=None):
    try:
        requests.post(
            f"{API_BASE_URL}?action=report_result",
            data={"job_id": job_id, "status": status, "error_info": error_info},
            headers=HEADERS,
            timeout=5
        )
    except Exception as e:
        print(f"[Worker] Report job error: {e}")

def run_worker():
    with sync_playwright() as p:
        # 持久化 Browser Context 目录，保障 session 会话连续性
        browser = p.chromium.launch_persistent_context(
        user_data_dir="./user_data", # 如果依然白屏，请先去手动删除这个文件夹！
        headless=False,
        user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        args=["--disable-blink-features=AutomationControlled"]
    )
        

        while True:
            send_heartbeat("authenticated")
            job = claim_job()
        
            if job:
                job_id = job['id']
                phone = f"+6{job['recipient']}"
                text = job['message']
                print(f"[Worker] Processing Job #{job_id} to {phone}")

                try:
                    # 使用 WhatsApp 官方 Direct Deep-link
                    #page.goto(f"https://web.whatsapp.com/send?phone={phone}&text={requests.utils.quote(text)}")
                    
                    page = browser.new_page()
                    page.goto(f"https://web.whatsapp.com/send?phone={phone}&text={text}")
                    print("[Worker] Persistent browser session loaded. Waiting for WhatsApp authentication...")
                    
                    # 等待输入框加载完成 (Adapter Selector)
                    #@page.wait_for_selector(input_selector, state="visible", timeout=30000)
                    input_box = page.wait_for_selector('div[contenteditable="true"][role="textbox"]', timeout=30000)
                    input_box.focus()
                    time.sleep(2) 
                    
                    
                    # 模拟 Enter 回车发送
                    page.keyboard.press("Enter")
                    time.sleep(3)
                    
                    report_job(job_id, "sent")
                    print(f"[Worker] Job #{job_id} SENT successfully.")
                except Exception as ex:
                    print(f"[Worker] Job #{job_id} FAILED: {ex}")
                    report_job(job_id, "failed", str(ex))

            time.sleep(5)

if __name__ == "__main__":
    run_worker()