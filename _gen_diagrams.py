# Generate ERD + Use-case diagrams as PNGs for the Drop IT docs.
# Reflects current code state (SMS removed, public tracking + cash-in + transit photos added).
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch, Ellipse, Rectangle
from matplotlib.lines import Line2D
from pathlib import Path

OUT = Path(r"C:\Users\LENOVO\OneDrive\Documents\WebDev\_docs_tmp")
OUT.mkdir(exist_ok=True)


# ======== ERD ========
def make_erd():
    tables = {
        'users': {
            'cols': ['id (PK)', 'username UK', 'email UK', 'password',
                     'role ENUM', 'full_name', 'phone',
                     'wallet_balance', 'cash_on_hand',
                     'availability', 'rating_avg', 'rating_count',
                     'referral_code', 'theme',
                     'phone_verified', 'email_verified',
                     'is_active', 'created_at'],
            'pos': (0.5, 6.5, 2.8, 3.0), 'color': '#dbeafe',
        },
        'parcels': {
            'cols': ['id (PK)', 'tracking_number UK', 'customer_id FK',
                     'driver_id FK',
                     'sender_name', 'pickup_address', 'pickup_lat/lng',
                     'recipient_name/phone/address', 'dropoff_lat/lng',
                     'product_name', 'weight', 'distance_km',
                     'amount', 'admin_fee', 'driver_earnings',
                     'payment_method', 'payment_status', 'payment_ref',
                     'accept_token', 'eta_minutes',
                     'pod_photo', 'pod_signature', 'pod_receiver',
                     'status', 'is_customer_order', 'is_walkin',
                     'created_at', 'updated_at'],
            'pos': (4.4, 4.8, 3.0, 4.7), 'color': '#fef3c7',
        },
        'parcel_history': {
            'cols': ['id (PK)', 'parcel_id FK', 'status',
                     'notes (incl. [IMG]... transit photos)',
                     'updated_by', 'created_at'],
            'pos': (8.6, 8.2, 2.6, 1.5), 'color': '#fee2e2',
        },
        'driver_locations': {
            'cols': ['driver_id (PK/FK)', 'lat', 'lng',
                     'accuracy', 'updated_at'],
            'pos': (0.5, 4.0, 2.8, 1.3), 'color': '#dcfce7',
        },
        'messages': {
            'cols': ['id (PK)', 'parcel_id FK', 'sender_id',
                     'sender_role', 'body', 'is_read', 'created_at'],
            'pos': (8.6, 6.0, 2.6, 1.7), 'color': '#fce7f3',
        },
        'ratings': {
            'cols': ['id (PK)', 'parcel_id UK', 'driver_id',
                     'customer_id', 'stars', 'review', 'created_at'],
            'pos': (8.6, 3.8, 2.6, 1.7), 'color': '#fae8ff',
        },
        'payments': {
            'cols': ['id (PK)', 'parcel_id FK', 'method', 'amount',
                     'status', 'provider_ref', 'checkout_url',
                     'created_at'],
            'pos': (4.6, 2.0, 2.6, 1.9), 'color': '#ede9fe',
        },
        'wallet_transactions': {
            'cols': ['id (PK)', 'user_id FK', 'parcel_id', 'amount',
                     'type (topup/delivery_earning/...)',
                     'description', 'balance_after', 'created_at'],
            'pos': (0.5, 1.8, 3.0, 1.9), 'color': '#fef9c3',
        },
        'notifications': {
            'cols': ['id (PK)', 'user_id', 'parcel_id', 'title',
                     'message', 'is_read', 'created_at'],
            'pos': (8.6, 1.8, 2.6, 1.7), 'color': '#e0e7ff',
        },
        'favorite_addresses': {
            'cols': ['id (PK)', 'user_id FK', 'label', 'address',
                     'lat', 'lng'],
            'pos': (8.6, 0.0, 2.6, 1.4), 'color': '#cffafe',
        },
        'otps': {
            'cols': ['id (PK)', "purpose = 'email'", 'identifier',
                     'code', 'session_id', 'expires_at', 'verified',
                     'created_at'],
            'pos': (4.6, -0.3, 2.6, 1.7), 'color': '#f5f5f4',
        },
        'reports': {
            'cols': ['id (PK)', 'user_id', 'subject', 'message',
                     'status', 'created_at'],
            'pos': (0.5, -0.4, 2.8, 1.5), 'color': '#fff7ed',
        },
        'returns': {
            'cols': ['id (PK)', 'parcel_id', 'customer_id',
                     'reason', 'status', 'admin_note'],
            'pos': (0.5, 10.0, 2.8, 1.5), 'color': '#ecfccb',
        },
        'sos_alerts': {
            'cols': ['id (PK)', 'driver_id', 'parcel_id', 'lat',
                     'lng', 'note', 'status', 'created_at'],
            'pos': (4.4, 10.1, 2.6, 1.6), 'color': '#ffe4e6',
        },
        'notification_log': {
            'cols': ['id (PK)', "channel (email | in_app)",
                     'recipient', 'subject', 'body', 'status',
                     'response', 'created_at'],
            'pos': (8.6, 10.2, 2.6, 1.5), 'color': '#e7e5e4',
        },
        'activity_logs': {
            'cols': ['id (PK)', 'user_id', 'action', 'details',
                     'created_at'],
            'pos': (12.0, 7.0, 2.3, 1.5), 'color': '#fde68a',
        },
        'settings': {
            'cols': ['setting_key (PK)', 'setting_value',
                     '(admin_email, smtp_*,', ' paymongo_*, app_secret)'],
            'pos': (12.0, 4.8, 2.3, 1.4), 'color': '#fecaca',
        },
    }

    fig, ax = plt.subplots(figsize=(20, 14))
    ax.set_xlim(-0.5, 15)
    ax.set_ylim(-1.5, 12.5)
    ax.axis('off')
    ax.set_title("Drop IT - Entity Relationship Diagram",
                 fontsize=22, fontweight='bold', pad=10)

    centers = {}
    for name, meta in tables.items():
        x, y, w, h = meta['pos']
        ax.add_patch(FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.04",
                                    linewidth=1.6, edgecolor='#0b1e3f',
                                    facecolor=meta['color']))
        ax.add_patch(Rectangle((x, y + h - 0.35), w, 0.35,
                               facecolor='#0b1e3f', edgecolor='#0b1e3f'))
        ax.text(x + w / 2, y + h - 0.18, name, ha='center', va='center',
                fontsize=11, fontweight='bold', color='white')
        col_y = y + h - 0.6
        for c in meta['cols']:
            ax.text(x + 0.1, col_y, c, ha='left', va='top',
                    fontsize=8, color='#111827', family='monospace')
            col_y -= 0.18
        centers[name] = (x + w / 2, y + h / 2)

    rels = [
        ('users', 'parcels', '1:N customer'),
        ('users', 'parcels', '1:N driver'),
        ('parcels', 'parcel_history', '1:N'),
        ('users', 'driver_locations', '1:1'),
        ('parcels', 'messages', '1:N'),
        ('parcels', 'ratings', '1:1'),
        ('parcels', 'payments', '1:N'),
        ('parcels', 'returns', '1:1'),
        ('users', 'wallet_transactions', '1:N'),
        ('users', 'notifications', '1:N'),
        ('users', 'favorite_addresses', '1:N'),
        ('users', 'reports', '1:N'),
        ('users', 'sos_alerts', '1:N'),
        ('users', 'activity_logs', '1:N'),
    ]
    for a, b, label in rels:
        (ax1, ay1) = centers[a]
        (ax2, ay2) = centers[b]
        arr = FancyArrowPatch((ax1, ay1), (ax2, ay2),
                              arrowstyle='->', mutation_scale=14,
                              linewidth=1.0, color='#1e40af', alpha=0.55,
                              connectionstyle="arc3,rad=0.08")
        ax.add_patch(arr)
        mx, my = (ax1 + ax2) / 2, (ay1 + ay2) / 2
        ax.text(mx, my, label, fontsize=6.5, color='#1e40af',
                ha='center', va='center',
                bbox=dict(boxstyle='round,pad=0.15', facecolor='white',
                          edgecolor='#1e40af', linewidth=0.6, alpha=0.9))

    plt.tight_layout()
    p = OUT / "erd.png"
    plt.savefig(p, dpi=150, bbox_inches='tight', facecolor='white')
    plt.close()
    return p


# ======== Use-case diagram ========
def make_use_case():
    fig, ax = plt.subplots(figsize=(16, 13))
    ax.set_xlim(0, 16)
    ax.set_ylim(0, 13)
    ax.axis('off')
    ax.set_title("Drop IT - Use Case Diagram",
                 fontsize=20, fontweight='bold', pad=10)

    boundary = FancyBboxPatch((3.2, 0.6), 9.6, 11.3,
                              boxstyle="round,pad=0.2",
                              linewidth=1.8, edgecolor='#0b1e3f',
                              facecolor='#f8fafc')
    ax.add_patch(boundary)
    ax.text(8, 11.7, "Drop IT System", fontsize=14, fontweight='bold',
            ha='center', va='center', color='#0b1e3f')

    actors = {
        'Guest (public)':  (1.2, 11.2),
        'Customer':        (1.2, 8.5),
        'Driver':          (1.2, 5.0),
        'Admin':           (14.8, 5.5),
        'PayMongo (ext)':  (14.8, 1.5),
    }
    for name, (x, y) in actors.items():
        ax.add_patch(plt.Circle((x, y + 0.35), 0.2, facecolor='#1e40af',
                                edgecolor='#0b1e3f', linewidth=1.5))
        ax.plot([x, x], [y + 0.15, y - 0.5], 'k-', linewidth=1.5)
        ax.plot([x - 0.3, x + 0.3], [y - 0.1, y - 0.1], 'k-', linewidth=1.5)
        ax.plot([x, x - 0.25], [y - 0.5, y - 0.95], 'k-', linewidth=1.5)
        ax.plot([x, x + 0.25], [y - 0.5, y - 0.95], 'k-', linewidth=1.5)
        ax.text(x, y - 1.2, name, ha='center', va='top', fontsize=9.5,
                fontweight='bold', color='#0b1e3f')

    def uc(x, y, label, color='#dbeafe'):
        e = Ellipse((x, y), 2.0, 0.55, facecolor=color,
                    edgecolor='#0b1e3f', linewidth=1.2)
        ax.add_patch(e)
        ax.text(x, y, label, ha='center', va='center',
                fontsize=8.5, color='#0b1e3f')

    guest_ucs = [
        (5.3, 11.1, 'Public track (masked)', '#e0f2fe'),
    ]
    cust_ucs = [
        (5.3, 10.2, 'Sign up + email OTP',       '#dbeafe'),
        (5.3, 9.3,  'Login / Logout',            '#dbeafe'),
        (5.3, 8.4,  'Place order',               '#dbeafe'),
        (5.3, 7.5,  'Track parcel (live)',       '#dbeafe'),
        (5.3, 6.6,  'Cash-in wallet (GCash)',    '#dbeafe'),
        (5.3, 5.7,  'Pay wallet / COD / GCash',  '#dbeafe'),
        (5.3, 4.8,  'Chat + voice call',         '#dbeafe'),
        (5.3, 3.9,  'Rate driver',               '#dbeafe'),
        (5.3, 3.0,  'Request return',            '#dbeafe'),
        (5.3, 2.1,  'Report / support chat',     '#dbeafe'),
        (5.3, 1.2,  'Manage favorites/profile',  '#dbeafe'),
    ]
    drv_ucs = [
        (8.0, 5.7, 'Accept / decline',          '#dcfce7'),
        (8.0, 4.8, 'Update status',             '#dcfce7'),
        (8.0, 3.9, 'Upload pickup photo',       '#dcfce7'),
        (8.0, 3.0, 'Send transit photo',        '#dcfce7'),
        (8.0, 2.1, 'Capture POD + collect COD', '#dcfce7'),
        (8.0, 1.2, 'Send SOS',                  '#dcfce7'),
    ]
    adm_ucs = [
        (10.7, 10.2, 'Manage accounts',         '#fef3c7'),
        (10.7, 9.3,  'Create walk-in parcel',   '#fef3c7'),
        (10.7, 8.4,  'Dispatch + cancel',       '#fef3c7'),
        (10.7, 7.5,  'Notify driver (email)',   '#fef3c7'),
        (10.7, 6.6,  'Fleet map (live GPS)',    '#fef3c7'),
        (10.7, 5.7,  'Inbox (SOS/reports/...)', '#fef3c7'),
        (10.7, 4.8,  'Analytics + reports',     '#fef3c7'),
        (10.7, 3.9,  'Confirm/reject return',   '#fef3c7'),
        (10.7, 3.0,  'Handle user reports',     '#fef3c7'),
        (10.7, 2.1,  'Settings (SMTP / Pay...)', '#fef3c7'),
        (10.7, 1.2,  'Audit history',           '#fef3c7'),
    ]
    pay_ucs = [
        (8.0, 0.9,  'Process GCash payment',    '#fecaca'),
    ]

    usecases = {}
    for lst, role in [(guest_ucs, 'Guest (public)'),
                      (cust_ucs, 'Customer'),
                      (drv_ucs, 'Driver'),
                      (adm_ucs, 'Admin'),
                      (pay_ucs, 'PayMongo (ext)')]:
        usecases[role] = []
        for x, y, lbl, c in lst:
            uc(x, y, lbl, c)
            usecases[role].append((x, y))

    actor_pos = dict(actors)
    for role, pts in usecases.items():
        ax_pt = actor_pos[role]
        for x, y in pts:
            ax.add_line(Line2D(
                [ax_pt[0] + (0.3 if ax_pt[0] < 8 else -0.3),
                 x - 1.0 if ax_pt[0] < 8 else x + 1.0],
                [ax_pt[1], y],
                color='#334155', linewidth=0.9, alpha=0.55))

    p = OUT / "usecase.png"
    plt.tight_layout()
    plt.savefig(p, dpi=150, bbox_inches='tight', facecolor='white')
    plt.close()
    return p


if __name__ == "__main__":
    e = make_erd()
    u = make_use_case()
    print("ERD:", e)
    print("UC :", u)
