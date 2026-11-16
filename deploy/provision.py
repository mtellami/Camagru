#!/usr/bin/env python3
"""Provision the free Render web service with the existing CLI login.

Requires Python 3 and PyYAML. Reads secrets from ignored .env.production and never
prints them. Run after Render allows free service creation for this account.
"""
import json
import shlex
import sys
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, urlopen

import yaml

ROOT = Path(__file__).resolve().parent.parent
config = yaml.safe_load((Path.home() / '.render/cli.yaml').read_text())
owner = config.get('workspace')
if not owner:
    sys.exit('Select a Render workspace with render workspace set first.')
values = {}
for line in (ROOT / '.env.production').read_text().splitlines():
    if line.strip() and not line.lstrip().startswith('#'):
        key, value = line.split('=', 1)
        tokens = shlex.split(value)
        values[key] = tokens[0] if tokens else ''
for key in ['APP_KEY', 'DB_HOST', 'DB_DIRECT_HOST', 'DB_PASSWORD', 'SMTP_USER', 'SMTP_PASSWORD', 'SMTP_FROM']:
    if not values.get(key) or 'GENERATE_ME' in values[key] or 'YOUR_' in values[key]:
        sys.exit('Fill ' + key + ' in .env.production before provisioning.')
values.update(AUTO_MIGRATE='1', MEDIA_DRIVER='pgsql', SESSION_DRIVER='pgsql', COOKIE_SECURE='1', SMTP_PORT='2525', SMTP_ENCRYPTION='starttls')

def api(method, path, body=None):
    request = Request('https://api.render.com/v1/' + path,
                      data=json.dumps(body).encode() if body is not None else None,
                      headers={'Authorization': 'Bearer ' + config['api']['key'], 'Content-Type': 'application/json'},
                      method=method)
    try:
        with urlopen(request, timeout=60) as response:
            raw = response.read()
            return json.loads(raw) if raw else None
    except HTTPError as error:
        if error.code == 402:
            sys.exit('Render requires payment information even for this free service. Visit https://dashboard.render.com/billing')
        sys.exit('Render API request failed (HTTP ' + str(error.code) + '). Credentials withheld.')

services = api('GET', 'services?ownerId=' + owner + '&limit=100')
service = next((item['service'] for item in services if item['service']['name'] == 'camagru'), None)
if service is None:
    response = api('POST', 'services', {
        'type': 'web_service', 'name': 'camagru', 'ownerId': owner,
        'repo': 'https://github.com/mtellami/camagru', 'branch': 'main',
        'autoDeployTrigger': 'off',
        'envVars': [{'key': key, 'value': value} for key, value in values.items()],
        'serviceDetails': {'env': 'docker', 'plan': 'free', 'region': 'ohio',
                           'healthCheckPath': '/health',
                           'envSpecificDetails': {'dockerfilePath': './docker/Dockerfile', 'dockerContext': '.'}},
    })
    service = response.get('service', response)
if service.get('serviceDetails', {}).get('plan') != 'free':
    sys.exit('Existing service is not on the free plan; refusing to change it.')
service_id = service['id']
url = service['serviceDetails']['url']
values['APP_URL'] = url
# Replace the locally managed environment as a unit; it is the configuration source.
api('PUT', 'services/' + service_id + '/env-vars', [{'key': key, 'value': value} for key, value in values.items()])
api('PATCH', 'services/' + service_id, {
    'autoDeployTrigger': 'checksPass',
    'serviceDetails': {'healthCheckPath': '/health', 'envSpecificDetails': {'dockerfilePath': './docker/Dockerfile', 'dockerContext': '.'}},
})
# Keep the resolved URL locally for subsequent provisioning runs.
(ROOT / '.env.production').write_text('\n'.join(key + '=' + shlex.quote(value) for key, value in values.items()) + '\n')
(ROOT / '.env.production').chmod(0o600)
(ROOT / 'deploy/render-service.json').write_text(json.dumps({'serviceId': service_id, 'url': url}, indent=2) + '\n')
print('Configured free Render service:', service_id, url)
print('Deploy with: render deploys create ' + service_id + ' --wait --output json')
