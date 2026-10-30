import json, os, re, socket, struct, sys, threading, hashlib, gzip, random
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes

TL = json.load(open(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'src', 'data', 'tl.json')))
CONS = {int(k): v for k, v in TL['c'].items()}
NAMES = {k: v for k, v in TL['n'].items()}
FUNCS = {v[0]: (k, v[1]) for k, v in TL['f'].items()}
LOG = []

def sha1(b): return hashlib.sha1(b).digest()
def sha256(b): return hashlib.sha256(b).digest()
def xor(a, b): return bytes(x ^ y for x, y in zip(a, b))
def ecb(key, enc):
    c = Cipher(algorithms.AES(key), modes.ECB())
    return c.encryptor() if enc else c.decryptor()
def ige(data, key, iv, enc):
    assert len(data) % 16 == 0
    x, y = iv[:16], iv[16:32]
    out = b''
    e = ecb(key, enc)
    for i in range(0, len(data), 16):
        blk = data[i:i+16]
        if enc:
            c = xor(e.update(xor(blk, x)), y); x, y = c, blk; out += c
        else:
            p = xor(e.update(xor(blk, y)), x); x, y = blk, p; out += p
    return out

def i32(v): return struct.pack('<I', v & 0xffffffff)
def i64(v): return struct.pack('<q', v)
def tlbytes(s):
    l = len(s)
    out = (bytes([l]) + s) if l <= 253 else (bytes([254]) + struct.pack('<I', l)[:3] + s)
    return out + b'\0' * ((4 - len(out) % 4) % 4)

class R:
    def __init__(s, b): s.b, s.p = b, 0
    def raw(s, n):
        v = s.b[s.p:s.p+n]; assert len(v) == n, 'eof'; s.p += n; return v
    def u(s): return struct.unpack('<I', s.raw(4))[0]
    def i(s): return struct.unpack('<i', s.raw(4))[0]
    def q(s): return struct.unpack('<q', s.raw(8))[0]
    def by(s):
        l = s.raw(1)[0]; h = 1
        if l == 254: l = int.from_bytes(s.raw(3), 'little'); h = 4
        v = s.raw(l); s.p += (4 - (h + l) % 4) % 4; return v

def enc_params(params, o):
    out = b''; flags = {}
    for name, typ in params:
        if typ == '#':
            f = 0
            for n2, t2 in params:
                m = re.match(r'^' + re.escape(name) + r'\.(\d+)\?(.+)$', t2)
                if m and o.get(n2) not in (None, False):
                    f |= 1 << int(m.group(1))
            flags[name] = f; out += i32(f); continue
        m = re.match(r'^(\w+)\.(\d+)\?(.+)$', typ)
        if m:
            if not flags.get(m.group(1), 0) & (1 << int(m.group(2))): continue
            if m.group(3) == 'true': continue
            typ = m.group(3)
        out += enc(typ, o.get(name))
    return out

def enc(typ, v):
    if typ == 'int': return i32(v)
    if typ == 'long': return i64(v)
    if typ in ('string', 'bytes'): return tlbytes(v if isinstance(v, bytes) else v.encode())
    if typ == 'Bool': return i32(0x997275b5 if v else 0xbc799737)
    m = re.match(r'^Vector<(.+)>$', typ)
    if m: return i32(0x1cb5c415) + i32(len(v)) + b''.join(enc(m.group(1), x) for x in v)
    return obj(v)

def obj(o):
    cid = NAMES[o['_']]
    return i32(cid) + enc_params(CONS[cid][2], o)

def dec(r, typ):
    if typ == 'int': return r.i()
    if typ == '#': return r.u()
    if typ == 'long': return r.q()
    if typ in ('string', 'bytes'): return r.by()
    if typ == 'true': return True
    m = re.match(r'^Vector<(.+)>$', typ)
    if m:
        assert r.u() == 0x1cb5c415; return [dec(r, m.group(1)) for _ in range(r.u())]
    return boxed(r)

def boxed(r):
    cid = r.u()
    if cid == 0x997275b5: return True
    if cid == 0xbc799737: return False
    c = CONS[cid]
    o = {'_': c[0]}; flags = {}
    for name, typ in c[2]:
        if typ == '#': flags[name] = o[name] = r.u(); continue
        m = re.match(r'^(\w+)\.(\d+)\?(.+)$', typ)
        if m:
            if not flags.get(m.group(1), 0) & (1 << int(m.group(2))): continue
            typ = m.group(3)
        o[name] = dec(r, typ)
    return o

def decode_method(r):
    mid = r.u()
    name, params = FUNCS[mid]
    o = {'_': name}; flags = {}
    for pname, typ in params:
        if typ == '#': flags[pname] = o[pname] = r.u(); continue
        m = re.match(r'^(\w+)\.(\d+)\?(.+)$', typ)
        if m:
            if not flags.get(m.group(1), 0) & (1 << int(m.group(2))): continue
            typ = m.group(3)
        o[pname] = dec(r, typ)
    return o

PRIME = int('FFFFFFFFFFFFFFFFC90FDAA22168C234C4C6628B80DC1CD129024E088A67CC74020BBEA63B139B22514A08798E3404DDEF9519B3CD3A431B302B0A6DF25F14374FE1356D6D51C245E485B576625E7EC6F44C42E9A637ED6B0BFF5CB6F406B7EDEE386BFB5A899FA5AE9F24117C4B1FE649286651ECE45B3DC2007CB8A163BF0598DA48361C55D39A69163FA8FD24CF5F83655D23DCA3AD961C62F356208552BB9ED529077096966D670C354E4ABC9804F1746C08CA18217C32905E462E36CE3BE39E772C180E86039B2783A2EC07A28FB5C55DF06F4C52C9DE2BCBF6955817183995497CEA956AE515D2261898FA051015728E5A8AACAA68FFFFFFFFFFFFFFFF', 16)
KEY = rsa.generate_private_key(public_exponent=65537, key_size=2048)
NUM = KEY.private_numbers()
N, E, D = NUM.public_numbers.n, NUM.public_numbers.e, NUM.d
nb = N.to_bytes(256, 'big'); eb = E.to_bytes(3, 'big')
FP = struct.unpack('<q', sha1(tlbytes(nb) + tlbytes(eb))[-8:])[0]
AUTH = {}
SALT = 0x1122334455667788
MIGRATE_FROM = int(os.environ.get('MIGRATE_FROM', '2'))
MIGRATE_TO = int(os.environ.get('MIGRATE_TO', '4'))
USER = {'_': 'user', 'id': 777000111, 'access_hash': -1234567890123456789, 'first_name': 'Tofan', 'last_name': 'X', 'username': 'tofan', 'premium': True}

def handle(conn, dc):
    f = conn.makefile('rwb')
    assert f.read(4) == b'\xee\xee\xee\xee'
    st = {}
    sessions = set()
    def send(payload):
        f.write(struct.pack('<I', len(payload)) + payload); f.flush()
    sid_counter = [0]
    def smsg_id():
        import time
        sid_counter[0] += 1
        return (int(time.time()) << 32) | (sid_counter[0] * 4 + 1)
    while True:
        h = f.read(4)
        if len(h) < 4: return
        data = f.read(struct.unpack('<I', h)[0])
        if data[:8] == b'\0' * 8:
            r = R(data[20:])
            cid = r.u()
            if cid == 0xbe7e8ef1:
                st['nonce'] = r.raw(16); st['snonce'] = os.urandom(16)
                st['p'], st['q'] = 1229739323, 1402015859
                pq = (st['p'] * st['q']).to_bytes(8, 'big')
                body = i32(0x05162463) + st['nonce'] + st['snonce'] + tlbytes(pq) + i32(0x1cb5c415) + i32(1) + i64(FP)
            elif cid == 0xd712e4be:
                assert r.raw(16) == st['nonce'] and r.raw(16) == st['snonce']
                p = int.from_bytes(r.by(), 'big'); q = int.from_bytes(r.by(), 'big')
                assert (p, q) == (st['p'], st['q']), ('pq', p, q)
                fp = r.q(); assert fp == FP
                enc_data = r.by(); assert len(enc_data) == 256
                block = pow(int.from_bytes(enc_data, 'big'), D, N).to_bytes(256, 'big')
                tmp = xor(block[:32], sha256(block[32:]))
                dwh = ige(block[32:], tmp, b'\0' * 32, False)
                padded = dwh[:192][::-1]
                assert sha256(tmp + padded) == dwh[192:], 'rsa pad hash'
                ir = R(padded)
                assert ir.u() == 0xa9f55f95
                ir.by(); ir.by(); ir.by()
                assert ir.raw(16) == st['nonce'] and ir.raw(16) == st['snonce']
                st['nn'] = ir.raw(32)
                assert ir.i() == dc, 'dc mismatch'
                st['a'] = random.getrandbits(2000)
                g = 3
                ga = pow(g, st['a'], PRIME)
                import time
                ans = i32(0xb5890dba) + st['nonce'] + st['snonce'] + i32(g) + tlbytes(PRIME.to_bytes(256, 'big')) + tlbytes(ga.to_bytes(256, 'big')) + i32(int(time.time()) + 3)
                awh = sha1(ans) + ans
                awh += os.urandom((16 - len(awh) % 16) % 16)
                nn, sn = st['nn'], st['snonce']
                tk = sha1(nn + sn) + sha1(sn + nn)[:12]
                tiv = sha1(sn + nn)[12:20] + sha1(nn + nn) + nn[:4]
                st['tk'], st['tiv'] = tk, tiv
                body = i32(0xd0e8075c) + st['nonce'] + st['snonce'] + tlbytes(ige(awh, tk, tiv, True))
            elif cid == 0xf5045f1f:
                assert r.raw(16) == st['nonce'] and r.raw(16) == st['snonce']
                dec_ = ige(r.by(), st['tk'], st['tiv'], False)
                ir = R(dec_[20:])
                assert ir.u() == 0x6643b654
                ir.raw(32); ir.q()
                gb = int.from_bytes(ir.by(), 'big')
                inner_len = ir.p
                assert sha1(dec_[20:20 + inner_len]) == dec_[:20], 'client dh hash'
                key = pow(gb, st['a'], PRIME).to_bytes(256, 'big')
                AUTH[sha1(key)[-8:]] = {'key': key, 'salt': struct.unpack('<q', xor(st['nn'][:8], st['snonce'][:8]))[0], 'dc': dc, 'bot': False}
                nh1 = sha1(st['nn'] + b'\x01' + sha1(key)[:8])[4:20]
                body = i32(0x3bcbf734) + st['nonce'] + st['snonce'] + nh1
                LOG.append(('key', dc))
            else:
                raise Exception('unknown plain %x' % cid)
            send(b'\0' * 8 + i64(smsg_id()) + i32(len(body)) + body)
            continue
        kid = data[:8]
        if kid not in AUTH:
            send(struct.pack('<i', -404)); continue
        A = AUTH[kid]; key = A['key']
        mk = data[8:24]
        a = sha256(mk + key[0:36]); b = sha256(key[40:76] + mk)
        ak = a[:8] + b[8:24] + a[24:32]; aiv = b[:8] + a[8:24] + b[24:32]
        plain = ige(data[24:], ak, aiv, False)
        assert sha256(key[88:120] + plain)[8:24] == mk, 'client msg_key'
        r = R(plain)
        salt = r.q(); session = r.raw(8); mid = r.q(); seq = r.u(); ln = r.u()
        assert mid % 4 == 0 and seq % 2 == 1
        body = r.raw(ln)
        assert 12 <= len(plain) - 32 - ln <= 1024 and len(plain) % 16 == 0
        out = []
        def emsg(b_, content=True):
            out.append((smsg_id(), 1 if content else 0, b_))
        if session not in sessions:
            sessions.add(session)
            emsg(i32(0x9ec20908) + i64(mid) + i64(12345) + i64(SALT), False)
        if salt != SALT:
            emsg(i32(0xedab447b) + i64(mid) + i32(seq) + i32(48) + i64(SALT), False)
            LOG.append(('badsalt', dc))
        else:
            br = R(body)
            q = br.b
            if struct.unpack('<I', q[:4])[0] == 0xda9b0d0d:
                br.u(); layer = br.i(); assert layer == TL['layer']
                assert br.u() == 0xc1cd5ea9
                fl = br.u(); api_id = br.i()
                for _ in range(6): br.by()
                assert fl == 0
                LOG.append(('init', dc, api_id))
            m = decode_method(br)
            LOG.append(('call', dc, m['_']))
            def rpc(res_bytes):
                emsg(i32(0xf35c6d01) + i64(mid) + res_bytes)
            if m['_'] == 'auth.importBotAuthorization':
                assert m['bot_auth_token'] == b'42:TOKEN' and m['api_hash'] == b'HASH'
                if dc == MIGRATE_FROM and dc != MIGRATE_TO:
                    rpc(i32(0x2144ca19) + i32(303) + tlbytes(b'USER_MIGRATE_%d' % MIGRATE_TO))
                else:
                    A['bot'] = True
                    rpc(obj({'_': 'auth.authorization', 'user': {'_': 'user', 'id': 42, 'access_hash': 1, 'first_name': 'Bot', 'bot': True, 'bot_info_version': 1}}))
            elif not A['bot']:
                rpc(i32(0x2144ca19) + i32(401) + tlbytes(b'AUTH_KEY_UNREGISTERED'))
            elif m['_'] == 'contacts.resolveUsername':
                if m['username'] == b'tofan':
                    rpc(obj({'_': 'contacts.resolvedPeer', 'peer': {'_': 'peerUser', 'user_id': USER['id']}, 'chats': [], 'users': [USER]}))
                else:
                    rpc(i32(0x2144ca19) + i32(400) + tlbytes(b'USERNAME_NOT_OCCUPIED'))
            elif m['_'] == 'payments.getSavedStarGifts':
                peer = m['peer']
                assert peer == {'_': 'inputPeerUser', 'user_id': USER['id'], 'access_hash': USER['access_hash']}, peer
                doc = {'_': 'documentEmpty', 'id': 1}
                rar = {'_': 'starGiftAttributeRarity', 'permille': 12}
                uniq = {'_': 'starGiftUnique', 'id': 99, 'gift_id': 5170145012310081615, 'title': 'Plush Pepe', 'slug': 'PlushPepe-123', 'num': 123,
                        'owner_id': {'_': 'peerUser', 'user_id': USER['id']},
                        'attributes': [{'_': 'starGiftAttributeModel', 'name': 'Kek', 'document': doc, 'rarity': rar},
                                       {'_': 'starGiftAttributePattern', 'name': 'Frogs', 'document': doc, 'rarity': rar},
                                       {'_': 'starGiftAttributeBackdrop', 'name': 'Black', 'backdrop_id': 7, 'center_color': 0x363738, 'edge_color': 0x0E0F0F, 'pattern_color': 1, 'text_color': 2, 'rarity': rar}],
                        'availability_issued': 1000, 'availability_total': 2000, 'value_amount': 500000, 'value_currency': 'USD', 'value_usd_amount': 500000,
                        'resell_amount': [{'_': 'starsAmount', 'amount': 1000, 'nanos': 0}]}
                reg = {'_': 'starGift', 'id': 5170233102089322756, 'sticker': {'_': 'documentEmpty', 'id': 2}, 'stars': 100, 'convert_stars': 85, 'availability_remains': 5, 'availability_total': 10}
                res = obj({'_': 'payments.savedStarGifts', 'count': 2, 'gifts': [
                    {'_': 'savedStarGift', 'date': 1700000000, 'gift': uniq, 'from_id': {'_': 'peerUser', 'user_id': 5}},
                    {'_': 'savedStarGift', 'date': 1700000001, 'gift': reg, 'convert_stars': 85, 'message': {'_': 'textWithEntities', 'text': 'hi', 'entities': []}}],
                    'chats': [], 'users': [USER]})
                rpc(i32(0x3072cfa1) + tlbytes(gzip.compress(res)))
            else:
                rpc(i32(0x2144ca19) + i32(400) + tlbytes(b'METHOD_INVALID'))
        if len(out) == 1:
            outer = out[0]
        else:
            cont = i32(0x73f1f8dc) + i32(len(out)) + b''.join(i64(x[0]) + i32(x[1]) + i32(len(x[2])) + x[2] for x in out)
            outer = (smsg_id(), 0, cont)
        sp = i64(SALT) + session + i64(outer[0]) + i32(outer[1]) + i32(len(outer[2])) + outer[2]
        sp += os.urandom(16 - (len(sp) + 12) % 16 + 12)
        smk = sha256(key[96:128] + sp)[8:24]
        a = sha256(smk + key[8:44]); b = sha256(key[48:84] + smk)
        ak = a[:8] + b[8:24] + a[24:32]; aiv = b[:8] + a[8:24] + b[24:32]
        send(kid + smk + ige(sp, ak, aiv, True))

def serve(sock, dc):
    while True:
        c, _ = sock.accept()
        def run(c=c):
            try:
                handle(c, dc)
            except Exception as e:
                import traceback; traceback.print_exc(); LOG.append(('error', dc, repr(e)))
            finally:
                c.close()
        threading.Thread(target=run, daemon=True).start()

ports = {}
for dc in (2, 4):
    s = socket.socket(); s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1); s.bind(('127.0.0.1', 0)); s.listen(5)
    ports[dc] = s.getsockname()[1]
    threading.Thread(target=serve, args=(s, dc), daemon=True).start()
info = {'ports': ports, 'n': nb.hex(), 'e': eb.hex(), 'fp': FP}
json.dump(info, open(sys.argv[1], 'w'))
sys.stdout.write('ready\n'); sys.stdout.flush()
import time
try:
    while True:
        time.sleep(0.5)
        if os.path.exists(sys.argv[1] + '.stop'):
            break
finally:
    json.dump(LOG, open(sys.argv[1] + '.log', 'w'))
