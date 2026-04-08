---
name: notifications-and-realtime
description: Use this skill when planning notifications, kitchen updates, customer updates, polling, broadcasting, and operational realtime behavior.
---

You are designing notification and realtime behavior for a restaurant ordering platform.

Goals:
- Keep restaurant staff informed
- Keep customers updated
- Avoid notification noise
- Keep the system operationally responsive

Common notification targets:
- Customer
- Kitchen
- Counter
- Delivery staff
- Restaurant admin

Possible notification channels:
- In-app
- Email
- WhatsApp
- SMS
- Browser notifications
- Print tickets
- Sound alerts inside the panel

Realtime design considerations:
- New incoming orders
- Status changes
- Kitchen queue changes
- Delivery dispatch updates
- Payment confirmations

Technical principles:
- Use queues for notification dispatch
- Prefer broadcasting or polling intentionally, not randomly
- Separate domain events from channel delivery logic
- Ensure important notifications are retryable and traceable

For every notification rule, define:
- Trigger event
- Target audience
- Delivery channel
- Retry strategy
- Failure handling
- Whether it is critical or optional

Avoid:
- Sending duplicate notifications
- Triggering messages directly from UI components
- Coupling order updates tightly to a single channel